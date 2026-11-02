<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Imports responder-facing questions without storing the upstream HTML/JSON body.
 * Uses the official Forms API when an OAuth token is configured, otherwise a
 * published responder form can be imported for local demo use.
 */
class GoogleFormQuestionImporter
{
    public function import(string $form): array
    {
        $formId = $this->formId($form);
        if ($formId === '') {
            throw new RuntimeException('Google Form ID hoặc URL không hợp lệ.');
        }

        $token = trim((string) config('research.google_forms_access_token'));

        return $token !== ''
            ? $this->importFromApi($formId, $token)
            : $this->importFromPublishedForm($formId);
    }

    private function importFromApi(string $formId, string $token): array
    {
        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(15)
                ->get('https://forms.googleapis.com/v1/forms/'.rawurlencode($formId));
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Không thể kết nối Google Forms API.', 0, $exception);
        }

        if (! $response->successful()) {
            $message = match ($response->status()) {
                401 => 'OAuth access token đã hết hạn hoặc không hợp lệ.',
                403 => 'Tài khoản OAuth không có quyền đọc Google Form này.',
                404 => 'Không tìm thấy Google Form.',
                default => 'Google Forms API trả về lỗi HTTP '.$response->status().'.',
            };
            throw new RuntimeException($message);
        }

        $form = $response->json();
        if (! is_array($form)) {
            throw new RuntimeException('Google Forms API trả về dữ liệu không hợp lệ.');
        }

        $questions = [];
        $warnings = [];
        foreach (($form['items'] ?? []) as $item) {
            if (! is_array($item) || ! isset($item['questionItem']['question'])) {
                if (isset($item['questionGroupItem'])) {
                    $warnings[] = 'Đã bỏ qua một câu hỏi dạng lưới.';
                }
                continue;
            }

            $question = $this->apiQuestion($item);
            if ($question === null) {
                $warnings[] = 'Đã bỏ qua câu hỏi không được website hỗ trợ: '.($item['title'] ?? 'không có tiêu đề').'.';
                continue;
            }
            $questions[] = $question;
        }

        return $this->result(
            (string) ($form['info']['title'] ?? 'Google Form'),
            (string) ($form['info']['description'] ?? ''),
            $questions,
            $warnings,
            'forms_api'
        );
    }

    private function apiQuestion(array $item): ?array
    {
        $source = $item['questionItem']['question'];
        $id = (string) ($source['questionId'] ?? '');
        if (! preg_match('/^[A-Za-z0-9_-]{1,100}$/', $id)) {
            return null;
        }

        $base = [
            'id' => $id,
            'entry_id' => $id,
            'title' => $this->text((string) ($item['title'] ?? 'Câu hỏi')),
            'description' => $this->text((string) ($item['description'] ?? '')),
            'required' => (bool) ($source['required'] ?? false),
            'options' => [],
        ];

        if (isset($source['textQuestion'])) {
            return $base + ['type' => ($source['textQuestion']['paragraph'] ?? false) ? 'paragraph' : 'short_text'];
        }

        if (isset($source['choiceQuestion'])) {
            $choice = $source['choiceQuestion'];
            $type = match ($choice['type'] ?? '') {
                'RADIO' => 'radio',
                'DROP_DOWN' => 'dropdown',
                'CHECKBOX' => 'checkbox',
                default => null,
            };
            if ($type === null) {
                return null;
            }
            $options = [];
            foreach (($choice['options'] ?? []) as $option) {
                $value = $this->text((string) ($option['value'] ?? ''));
                if ($value !== '' && ! ($option['isOther'] ?? false)) {
                    $options[] = ['value' => $value, 'label' => $value];
                }
            }
            return $options === [] ? null : $base + ['type' => $type, 'options' => $options];
        }

        if (isset($source['scaleQuestion'])) {
            $scale = $source['scaleQuestion'];
            $low = (int) ($scale['low'] ?? 1);
            $high = (int) ($scale['high'] ?? 5);
            if ($low > $high || $high - $low > 20) {
                return null;
            }
            $options = [];
            for ($value = $low; $value <= $high; $value++) {
                $options[] = ['value' => (string) $value, 'label' => (string) $value];
            }
            return $base + [
                'type' => 'scale',
                'options' => $options,
                'low_label' => $this->text((string) ($scale['lowLabel'] ?? '')),
                'high_label' => $this->text((string) ($scale['highLabel'] ?? '')),
            ];
        }

        return null;
    }

    private function importFromPublishedForm(string $formId): array
    {
        $url = 'https://docs.google.com/forms/d/e/'.rawurlencode($formId).'/viewform';
        $response = app(ConnectionHttpClient::class)->get($url, microtime(true) + 20, false);

        if (isset($response['error'])) {
            throw new RuntimeException('Không đọc được Google Form công khai: '.$response['error'].'.');
        }

        $html = (string) ($response['body'] ?? '');
        if (! preg_match('/FB_PUBLIC_LOAD_DATA_\s*=\s*(.*?);\s*<\/script>/s', $html, $match)) {
            throw new RuntimeException('Không tìm thấy cấu trúc câu hỏi. Hãy mở quyền trả lời bằng liên kết hoặc cấu hình OAuth Forms API.');
        }

        try {
            $payload = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('Dữ liệu câu hỏi Google Form không hợp lệ.', 0, $exception);
        }

        $form = $payload[1] ?? null;
        $items = is_array($form) ? ($form[1] ?? null) : null;
        if (! is_array($items)) {
            throw new RuntimeException('Google Form không chứa danh sách câu hỏi có thể đồng bộ.');
        }

        $questions = [];
        $warnings = [];
        foreach (array_slice($items, 0, 100) as $item) {
            $question = $this->publishedQuestion($item, $warnings);
            if ($question !== null) {
                $questions[] = $question;
            }
        }

        return $this->result(
            $this->text((string) ($form[8] ?? 'Google Form')),
            $this->text((string) ($form[0] ?? '')),
            $questions,
            $warnings,
            'published_form'
        );
    }

    private function publishedQuestion(mixed $item, array &$warnings): ?array
    {
        if (! is_array($item) || ! isset($item[3], $item[4][0]) || ! is_array($item[4][0])) {
            return null;
        }

        $typeCode = (int) $item[3];
        $type = [0 => 'short_text', 1 => 'paragraph', 2 => 'radio', 3 => 'dropdown', 4 => 'checkbox', 5 => 'scale'][$typeCode] ?? null;
        if ($type === null) {
            $warnings[] = 'Đã bỏ qua câu hỏi không được website hỗ trợ: '.$this->text((string) ($item[1] ?? 'không có tiêu đề')).'.';
            return null;
        }

        $entry = $item[4][0];
        $entryId = (string) ($entry[0] ?? '');
        if (! preg_match('/^\d{1,30}$/', $entryId)) {
            return null;
        }

        $options = [];
        if (in_array($type, ['radio', 'dropdown', 'checkbox', 'scale'], true)) {
            foreach (array_slice(is_array($entry[1] ?? null) ? $entry[1] : [], 0, 100) as $option) {
                if (! is_array($option)) {
                    continue;
                }
                $value = $this->text((string) ($option[0] ?? ''));
                $isOther = ($option[4] ?? 0) === 1;
                if ($isOther) {
                    $warnings[] = 'Lựa chọn “Khác” của câu “'.$this->text((string) ($item[1] ?? '')).'” chưa được nhập vì cần trường phụ của Google.';
                    continue;
                }
                if ($value !== '') {
                    $options[] = ['value' => $value, 'label' => $value];
                }
            }
            if ($options === []) {
                return null;
            }
        }

        return [
            'id' => $entryId,
            'entry_id' => $entryId,
            'title' => $this->text((string) ($item[1] ?? 'Câu hỏi')),
            'description' => $this->text((string) ($item[2] ?? '')),
            'type' => $type,
            'required' => (bool) ($entry[2] ?? false),
            'options' => $options,
            'low_label' => $type === 'scale' ? $this->text((string) ($entry[3][0] ?? '')) : '',
            'high_label' => $type === 'scale' ? $this->text((string) ($entry[3][1] ?? '')) : '',
        ];
    }

    private function result(string $title, string $description, array $questions, array $warnings, string $source): array
    {
        if ($questions === []) {
            throw new RuntimeException('Không tìm thấy câu hỏi được website hỗ trợ.');
        }

        return [
            'title' => mb_substr($title, 0, 300),
            'description' => mb_substr($description, 0, 3000),
            'questions' => array_values($questions),
            'warnings' => array_values(array_unique($warnings)),
            'source' => $source,
            'synced_at' => now('UTC')->toIso8601String(),
        ];
    }

    private function formId(string $value): string
    {
        if (preg_match('~/forms/d/e/([A-Za-z0-9_-]+)~', $value, $match)
            || preg_match('~/forms/d/([A-Za-z0-9_-]+)~', $value, $match)) {
            return $match[1];
        }

        return preg_replace('/[^A-Za-z0-9_-]/', '', $value) ?? '';
    }

    private function text(string $value): string
    {
        return trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\ResearchSettings;
use App\Http\Requests\UpdateResearchSettingsRequest;
use App\Exceptions\SettingsStorageException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AdminResearchSettingsController extends Controller
{
    public function edit(ResearchSettings $settings)
    {
        $configuration = $settings->all();
        return view('admin.research_settings', ['research'=>$configuration['research'], 'revision'=>$configuration['_revision'], 'preview'=>false]);
    }

    public function preview(UpdateResearchSettingsRequest $request)
    {
        return view('admin.research_settings', ['research'=>$request->research(), 'revision'=>$request->validated('revision'), 'preview'=>true]);
    }

    public function save(UpdateResearchSettingsRequest $request, ResearchSettings $settings)
    {
        try {
            $settings->updateResearch($request->research(), $request->validated('revision'), (string) $request->session()->get('admin_email', 'admin'));
        } catch (ConflictHttpException|SettingsStorageException $error) {
            return response()->view('admin.research_settings', ['research'=>$request->research(), 'revision'=>$request->validated('revision'), 'preview'=>false, 'saveError'=>$error->getMessage()], $error instanceof ConflictHttpException ? 409 : 503);
        }
        return redirect()->route('admin.settings.research.edit')->with('ok', 'Đã lưu thông tin nghiên cứu. Trang người tham gia sẽ cập nhật khi tải lại.');
    }
}

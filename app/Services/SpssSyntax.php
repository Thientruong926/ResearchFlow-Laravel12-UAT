<?php
namespace App\Services;

/** SPSS GET DATA syntax for the matching UTF-8 CSV export.
 *  Numeric columns remain numeric; categorical leading-zero codes remain strings.
 *  Scoring/reverse coding is intentionally NOT inferred without an approved codebook.
 */
class SpssSyntax
{
    private static function numericChoices(object $q): bool
    {
        if (!in_array($q->type, ['single', 'likert'], true)) return false;
        $values = json_decode($q->choices ?? '{}', true);
        if (!$values || !is_array($values)) return false;
        foreach (array_keys($values) as $code) {
            // Do not destroy identifiers such as "01" by converting them to numbers.
            if (!preg_match('/^-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?$/D', (string)$code)) return false;
        }
        return true;
    }

    public static function build(array $headers, iterable $questions): string
    {
        $byName = [];
        foreach ($questions as $q) $byName['Q'.$q->survey_id.'_'.strtoupper($q->code)] = $q;
        $script = "* ResearchFlow: load the CSV with the SAME scope (raw/clean) as this syntax.\n"
            . "* Change only the CSV path; do not rename source columns.\n"
            . "* Reverse scoring/derived variables require a separately approved analysis plan.\n"
            . "SET UNICODE=ON.\nGET DATA /TYPE=TXT\n /FILE='CHANGE_TO_FULL_CSV_PATH.csv'\n"
            . " /ENCODING='UTF8'\n /DELCASE=LINE\n /DELIMITERS=\",\"\n /QUALIFIER='\"'\n"
            . " /ARRANGEMENT=DELIMITED\n /FIRSTCASE=2\n /VARIABLES=\n";
        $numeric = [];
        foreach ($headers as $name) {
            $format = 'A255';
            if ($name === 'RESEARCH_ID') $format = 'A36';
            elseif (in_array($name,['SITE_CODE','WAVE_CODE','GROUP_CODE','QC_STATUS'],true)) $format = 'A64';
            elseif ($name === 'COMPLETED_AT') $format = 'A32';
            elseif (in_array($name,['SURVEY_VERSION','INFO_VERSION'],true)) $format = 'F10.0';
            elseif (isset($byName[$name])) {
                $q = $byName[$name];
                if ($q->type === 'number') $format = 'F24.4';
                elseif (self::numericChoices($q)) $format = 'F24.4';
                elseif ($q->type === 'text') $format = 'A5000';
            }
            $numeric[$name] = str_starts_with($format,'F');
            $script .= '  '.$name.' '.$format."\n";
        }
        $script .= ".\nEXECUTE.\n";
        foreach ($byName as $name => $q) {
            $label = str_replace("'", "''", preg_replace('/\s+/u',' ', $q->variable_label ?: $q->label) ?? $q->label);
            $script .= "VARIABLE LABELS $name '$label'.\n";
            $choices = json_decode($q->choices ?? '{}',true) ?: [];
            if ($choices) {
                $labels = [];
                foreach ($choices as $code => $valueLabel) {
                    $code = (string)$code;
                    $quoteCode = $numeric[$name] ? $code : "'".str_replace("'", "''", $code)."'";
                    $labels[] = $quoteCode." '".str_replace("'", "''",(string)$valueLabel)."'";
                }
                $script .= "VALUE LABELS $name ".implode(' ',$labels).".\n";
            }
            if ($q->missing_code !== null && $q->missing_code !== '') {
                $code = (string)$q->missing_code;
                if (!$numeric[$name]) $script .= "MISSING VALUES $name ('".str_replace("'", "''",$code)."').\n";
                elseif (preg_match('/^-?[0-9]+(?:\.[0-9]+)?$/D',$code)) $script .= "MISSING VALUES $name ($code).\n";
                else $script .= "* NOTE: nonnumeric missing code for numeric $name requires scientific review.\n";
            }
            $level = match($q->type) {'number'=>'SCALE','likert'=>'ORDINAL','single'=>'NOMINAL',default=>'NOMINAL'};
            $script .= "VARIABLE LEVEL $name ($level).\n";
        }
        return $script."EXECUTE.\n";
    }
}

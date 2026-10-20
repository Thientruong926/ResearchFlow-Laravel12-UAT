<?php
namespace Tests\Unit;

use App\Services\SpssSyntax;
use PHPUnit\Framework\TestCase;

class SpssSyntaxTest extends TestCase
{
    public function test_numeric_likert_and_number_import_as_numeric_with_labels():void
    {
        $q=(object)['survey_id'=>7,'code'=>'Q1','type'=>'likert','label'=>'Giá trị','variable_label'=>'Giá trị',
            'choices'=>'{"1":"Có","2":"Không"}','missing_code'=>'9'];
        $number=(object)['survey_id'=>7,'code'=>'Q2','type'=>'number','label'=>'Số','variable_label'=>'Số',
            'choices'=>'{}','missing_code'=>null];
        $s=SpssSyntax::build(['RESEARCH_ID','SURVEY_VERSION','Q7_Q1','Q7_Q2'],[$q,$number]);
        self::assertStringContainsString('Q7_Q1 F24.4',$s);
        self::assertStringContainsString('Q7_Q2 F24.4',$s);
        self::assertStringContainsString("VALUE LABELS Q7_Q1 1 'Có' 2 'Không'.",$s);
        self::assertStringContainsString('MISSING VALUES Q7_Q1 (9).',$s);
    }
    public function test_leading_zero_choice_codes_stay_strings():void
    {
        $q=(object)['survey_id'=>3,'code'=>'CODE','type'=>'single','label'=>'Mã','variable_label'=>'Mã',
            'choices'=>'{"01":"One","02":"Two"}','missing_code'=>null];
        $s=SpssSyntax::build(['Q3_CODE'],[$q]);
        self::assertStringContainsString('Q3_CODE A255',$s);
        self::assertStringContainsString("'01' 'One'",$s);
    }
}

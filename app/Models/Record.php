<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Record extends Model {
 protected $guarded=[];
 public function getCasts(): array { return array_merge(parent::getCasts(), ['payload'=>'array', 'choices'=>'array','condition'=>'array','value'=>'array','metadata'=>'array']); }
}

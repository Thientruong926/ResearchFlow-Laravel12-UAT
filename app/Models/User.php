<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
class User extends Authenticatable {
 use Notifiable;
 protected $fillable=['name','email','password','role','assigned_site_id'];
 protected $hidden=['password','remember_token'];
 protected $casts=['password'=>'hashed','active'=>'boolean'];
 public function hasRole(...$roles):bool { return in_array($this->role,$roles,true); }
}

<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\User;
class DatabaseSeeder extends Seeder {
 public function run(): void {
   $email = env('SEED_ADMIN_EMAIL'); $password=env('SEED_ADMIN_PASSWORD');
   if (!$email || !$password) { $this->command?->warn('Không tạo admin: cấu hình SEED_ADMIN_EMAIL và SEED_ADMIN_PASSWORD trong .env.'); return; }
   if (strlen($password)<12) throw new \RuntimeException('Mật khẩu quản trị phải có ít nhất 12 ký tự.');
   User::updateOrCreate(['email'=>$email],['name'=>'Research owner', 'password'=>$password,'role'=>'owner']);
   $this->command?->info('Tạo tài khoản owner. Xóa SEED_ADMIN_PASSWORD khỏi .env sau khi seed.');
 }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Spatie\Permission\Models\Role;

class CreateAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:admin {email} {--name=Admin} {--password=password}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tạo user admin với quyền Super Admin';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $name = $this->option('name');
        $password = $this->option('password');

        // Kiểm tra user đã tồn tại
        if (User::where('email', $email)->exists()) {
            $this->error("User với email {$email} đã tồn tại!");
            return 1;
        }

        // Kiểm tra role Super Admin
        $superAdminRole = Role::where('name', 'Super Admin')->first();
        if (!$superAdminRole) {
            $this->error("Role 'Super Admin' chưa được tạo. Hãy chạy seeder trước!");
            return 1;
        }

        // Tạo user
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt($password),
            'email_verified_at' => now(),
        ]);

        // Gán role Super Admin
        $user->assignRole('Super Admin');

        $this->info("User admin đã được tạo thành công!");
        $this->info("Email: {$email}");
        $this->info("Password: {$password}");
        $this->info("Role: Super Admin");

        return 0;
    }
}


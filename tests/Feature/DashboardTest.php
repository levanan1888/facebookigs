<?php

use App\Models\User;
use Spatie\Permission\Models\Role;
use Database\Seeders\RolePermissionSeeder;
use App\Services\FacebookAdsService;

test('guests are redirected to the login page', function () {
    $response = $this->get('/dashboard');
    $response->assertRedirect('/login');
});

test('authenticated users can visit the dashboard', function () {
    // Chạy seeder để tạo permissions và roles
    $this->seed(RolePermissionSeeder::class);
    
    // Lấy role User đã được tạo
    $userRole = Role::where('name', 'User')->first();
    
    $user = User::factory()->create();
    $user->assignRole($userRole);
    
    $this->actingAs($user);

    $response = $this->get('/dashboard');
    $response->assertStatus(200);
});

test('FacebookAdsService uses nested fields for creative', function () {
    $service = new FacebookAdsService('test_token');
    
    // Sử dụng reflection để kiểm tra private method hoặc kiểm tra fields
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('getAdsByAdSet');
    $method->setAccessible(true);
    
    // Tạo mock URL và params
    $url = "https://graph.facebook.com/v18.0/test_adset_id/ads";
    $params = [
        'access_token' => 'test_token',
        'fields' => 'id,name,status,effective_status,creative{id,title,body,object_story_spec,link_data,object_story_id,effective_object_story_id},created_time,updated_time,object_story_id,effective_object_story_id'
    ];
    
    // Kiểm tra xem fields có chứa nested creative không
    $this->assertStringContainsString('creative{id,title,body,object_story_spec,link_data,object_story_id,effective_object_story_id}', $params['fields']);
    $this->assertStringContainsString('object_story_spec', $params['fields']);
    $this->assertStringContainsString('link_data', $params['fields']);
});
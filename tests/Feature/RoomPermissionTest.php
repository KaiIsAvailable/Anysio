<?php

namespace Tests\Feature;

use App\Http\Controllers\RoomController;
use App\Models\{Room, RoomAsset, User};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Gate, Schema};
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoomPermissionTest extends TestCase
{
    public static function writeActions(): array
    {
        return [
            ['store', 'room.create'],
            ['update', 'room.edit'],
            ['destroy', 'room.delete'],
            ['restore', 'room.delete'],
            ['assetStore', 'room.edit'],
            ['assetUpdate', 'room.edit'],
            ['assetDestroy', 'room.edit'],
        ];
    }

    #[DataProvider('writeActions')]
    public function test_room_writes_without_gate_permission_are_rejected(string $action, string $permission): void
    {
        Gate::shouldReceive('denies')->once()->with('super-admin')->andReturn(true);
        Gate::shouldReceive('denies')->once()->with($permission)->andReturn(true);

        $request = Request::create('/room-action', 'POST');
        $room = new Room();
        $asset = new RoomAsset();
        $arguments = match ($action) {
            'store' => [$request],
            'update', 'assetStore' => [$request, $room],
            'assetUpdate' => [$request, $room, $asset],
            'assetDestroy' => [$room, $asset],
            default => [$room],
        };
        $response = app(RoomController::class)->$action(...$arguments);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('You have no permission.', session('error'));
    }

    public function test_permitted_staff_room_update_saves_without_a_separate_role_check(): void
    {
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Schema::create('rooms', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('unit_id');
            $table->string('room_no');
            $table->string('room_type');
            $table->string('status');
            $table->timestamps();
        });
        Schema::create('asset_room', function (Blueprint $table) {
            $table->id();
            $table->string('room_id');
            $table->string('status');
            $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->string('id')->primary();
            foreach (['user_id', 'event', 'auditable_type', 'auditable_id', 'old_values', 'new_values', 'ip_address', 'user_agent'] as $column) {
                $table->text($column)->nullable();
            }
            $table->timestamps();
        });
        DB::table('rooms')->insert(['id' => 'room', 'unit_id' => 'unit', 'room_no' => 'Old', 'room_type' => 'Single', 'status' => 'Occupied']);
        $staff = new User();
        $staff->id = 'staff';
        $staff->role = 'staff';
        $this->actingAs($staff);
        Gate::shouldReceive('denies')->once()->with('super-admin')->andReturn(true);
        Gate::shouldReceive('denies')->once()->with('room.edit')->andReturn(false);

        $response = app(RoomController::class)->update(Request::create('/room-action', 'PUT', [
            'room_no' => 'Updated', 'room_type' => 'Single', 'status' => 'Occupied',
        ]), Room::findOrFail('room'));

        $this->assertSame('Updated', Room::findOrFail('room')->room_no);
        $this->assertSame(route('admin.units.show', 'unit'), $response->getTargetUrl());
        $this->assertSame('Room and assets updated successfully.', session('success'));
    }

    public function test_permitted_staff_update_reaches_validation(): void
    {
        $staff = new User();
        $staff->id = 'staff';
        $staff->role = 'staff';
        $this->actingAs($staff);
        Gate::shouldReceive('denies')->once()->with('super-admin')->andReturn(true);
        Gate::shouldReceive('denies')->once()->with('room.edit')->andReturn(false);

        $this->expectException(ValidationException::class);
        app(RoomController::class)->update(Request::create('/room-action', 'PUT'), new Room());
    }
}

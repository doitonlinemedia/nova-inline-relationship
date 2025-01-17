<?php

namespace KirschbaumDevelopment\NovaInlineRelationship\Tests\Unit;

use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use KirschbaumDevelopment\NovaInlineRelationship\Tests\Profile;
use KirschbaumDevelopment\NovaInlineRelationship\Tests\Employee;
use KirschbaumDevelopment\NovaInlineRelationship\Tests\TestCase;
use KirschbaumDevelopment\NovaInlineRelationship\Tests\Resource\Employee as EmployeeResource;

class HasOneTest extends TestCase
{
    use WithFaker, RefreshDatabase;

    /**
     * @before
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->employeeResource = new EmployeeResource($this->employeeModel);
    }

    public function testResolveEmpty()
    {
        $inlineField = $this->employeeResource->resolveFieldForAttribute(new NovaRequest(), 'profile');

        $this->assertEmpty($inlineField->value);
    }

    public function testResolveWithRelationship()
    {
        $this->employeeModel->profile()->save(Profile::make(['phone' => '123234234']));

        $inlineField = $this->employeeResource->resolveFieldForAttribute(new NovaRequest(), 'profile');

        $this->assertCount(1, $inlineField->value);

        tap($inlineField->value->first(), function ($profile) {
            $this->assertArrayHasKey('phone', $profile->all());
            tap($profile->get('phone'), function ($phone) {
                $this->assertEquals(Text::class, $phone['component']);
                $this->assertEquals('phone', $phone['attribute']);
                tap($phone['meta'], function ($meta) {
                    $this->assertEquals('text-field', $meta['component']);
                    $this->assertEquals('123234234', $meta['value']);
                });
            });
        });
    }

    public function testFillAttributeForCreate()
    {
        $request = [
            'name' => 'Test',
            'profile' => [
                [
                    'phone' => '123123123',
                ],
            ],
        ];

        $newEmployee = new Employee();

        $this->employeeResource->fill(new NovaRequest($request), $newEmployee);

        $this->assertEmpty($newEmployee->profile);

        $newEmployee->save();

        tap($newEmployee->fresh()->profile, function ($profile) {
            $this->assertNotEmpty($profile);
            $this->assertEquals('123123123', $profile->phone);
        });
    }

    public function testFillAttributeForUpdate()
    {
        $newEmployee = Employee::create(['name' => 'Test']);
        $newEmployee->profile()->save(Profile::make(['phone' => '123123123']));

        $id = $newEmployee->fresh()->profile->id;

        $updateRequest = [
            'name' => 'Test 2',
            'profile' => [
                [
                    'phone' => '456456456',
                ],
            ],
        ];

        $this->employeeResource->fillForUpdate(new NovaRequest($updateRequest), $newEmployee);

        $newEmployee->save();

        tap($newEmployee->fresh()->profile, function ($profile) use ($id) {
            $this->assertEquals('456456456', $profile->phone);
            $this->assertEquals($id, $profile->id);
        });
    }

    public function testFillAttributeForDelete()
    {
        $newEmployee = Employee::create(['name' => 'Test']);
        $newEmployee->profile()->save(Profile::make(['phone' => '123123123']));

        $id = $newEmployee->fresh()->profile->id;

        $updateRequest = [
            'name' => 'Test 2',
            'profile' => [
            ],
        ];

        $this->employeeResource->fillForUpdate(new NovaRequest($updateRequest), $newEmployee);

        $this->assertNotEmpty($newEmployee->profile);

        $newEmployee->save();

        $this->assertEmpty($newEmployee->fresh()->profile);
    }

    public function testRuleIsEnforced()
    {
        $request = [
            'name' => 'Test',
            'profile' => [
                [
                    'phone' => null,
                ],
            ],
        ];

        $this->employeeResource->resolveFieldForAttribute(new NovaRequest(), 'profile');

        $this->expectException(ValidationException::class);
        $this->employeeResource::validateForUpdate(new NovaRequest($request));
    }
}

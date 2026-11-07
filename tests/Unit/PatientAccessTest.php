<?php

namespace Tests\Unit;

use App\Http\Middleware\EnsureAssignedPatient;
use App\Models\User;
use App\Services\PatientAccessService;
use App\Services\PermissionService;
use App\Services\TenantService;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class PatientAccessTest extends TestCase
{
    /** A nurse assigned to p1, or (with $permissions) anyone else. */
    private function access(array $permissions = [], array $assigned = ['p1']): PatientAccessService
    {
        $roles = $this->createMock(PermissionService::class);
        $roles->method('allows')->willReturnCallback(fn ($user, $permission) => in_array($permission, $permissions, true));

        $access = $this->getMockBuilder(PatientAccessService::class)
            ->setConstructorArgs([$roles, $this->createMock(TenantService::class)])
            ->onlyMethods(['assignedIds'])
            ->getMock();
        $access->method('assignedIds')->willReturn($assigned);

        return $access;
    }

    private function user(): User
    {
        return new User(['username' => 'nurse1']);
    }

    /** Runs the middleware for a request; 200 means it let the request through. */
    private function statusFor(PatientAccessService $access, array $input = [], array $route = []): int
    {
        $request = Request::create('/api/x', 'POST', $input);
        $request->setUserResolver(fn () => $this->user());
        $request->setRouteResolver(function () use ($request, $route) {
            $laravelRoute = (new LaravelRoute('POST', 'x', fn () => null))->bind($request);
            $laravelRoute->parameters = $route;

            return $laravelRoute;
        });

        return (new EnsureAssignedPatient($access))
            ->handle($request, fn () => new Response('', 200))
            ->getStatusCode();
    }

    public function test_a_nurse_reaches_an_assigned_patient(): void
    {
        $this->assertSame(200, $this->statusFor($this->access(), route: ['username' => 'p1']));
    }

    public function test_a_nurse_cannot_reach_another_patient(): void
    {
        $this->assertSame(403, $this->statusFor($this->access(), route: ['username' => 'p2']));
    }

    public function test_someone_with_no_duties_reaches_nobody(): void
    {
        $this->assertSame(403, $this->statusFor($this->access(assigned: []), route: ['username' => 'p1']));
    }

    public function test_admins_reach_every_patient(): void
    {
        $this->assertSame(200, $this->statusFor($this->access(['patients.view_all'], []), route: ['username' => 'p2']));
    }

    /** A role that schedules duties, or one given it by mistake, is still not an admin. */
    public function test_scheduling_does_not_reveal_every_patient(): void
    {
        $this->assertSame(403, $this->statusFor($this->access(['schedules.manage', 'schedules.view']), route: ['username' => 'p2']));
    }

    public function test_a_caretaker_gets_only_their_own_updates(): void
    {
        $day = collect([
            ['patient' => 'prakash', 'staff_id' => 'nurse1'],
            ['patient' => 'prakash', 'staff_id' => 'praveen'],
            ['patient' => 'sunil', 'staff_id' => 'praveen'],
        ]);

        $own = $this->access(assigned: ['prakash'])->ownDuties($this->user(), $day);

        $this->assertSame([['patient' => 'prakash', 'staff_id' => 'nurse1']], $own->all());
    }

    public function test_admins_get_every_caretaker_update(): void
    {
        $day = collect([['staff_id' => 'nurse1'], ['staff_id' => 'praveen']]);

        $this->assertCount(2, $this->access(['patients.view_all'], [])->ownDuties($this->user(), $day));
    }

    public function test_a_medication_id_in_the_url_is_not_taken_for_a_patient(): void
    {
        $this->assertSame(200, $this->statusFor($this->access(), route: ['username' => 'p1', 'id' => 'medication-1']));
    }

    public function test_a_legacy_request_is_checked_on_its_patient_field(): void
    {
        $this->assertSame(200, $this->statusFor($this->access(), ['patient_id' => 'p1']));
        $this->assertSame(403, $this->statusFor($this->access(), ['patient_id' => 'p2']));
    }

    public function test_a_legacy_request_naming_no_patient_is_refused(): void
    {
        $this->assertSame(403, $this->statusFor($this->access()));
    }

    public function test_an_allowed_patient_cannot_cover_for_another_in_a_second_field(): void
    {
        $this->assertSame(403, $this->statusFor($this->access(), ['patient_id' => 'p1', 'id' => 'p2']));
        $this->assertSame(403, $this->statusFor($this->access(), ['patient_id' => 'p1'], ['id' => 'p2']));
    }

    public function test_lists_keep_only_assigned_patients(): void
    {
        $rows = collect([['patient_id' => 'p1'], ['patient_id' => 'p2']]);

        $this->assertSame([['patient_id' => 'p1']], $this->access()->filter($this->user(), $rows)->all());
    }

    public function test_admins_get_the_whole_list(): void
    {
        $rows = collect([['patient_id' => 'p1'], ['patient_id' => 'p2']]);

        $this->assertCount(2, $this->access(['patients.view_all'], [])->filter($this->user(), $rows));
    }

    /** New routes under patients/{username} must not lose the check by accident. */
    public function test_every_route_about_one_patient_checks_the_assignment(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (LaravelRoute $route) => str_starts_with($route->uri(), 'api/v1/patients/{username}'));

        $this->assertGreaterThan(20, $routes->count());

        foreach ($routes as $route) {
            $this->assertContains('assigned.patient', $route->gatherMiddleware(), $route->uri());
        }
    }

    public function test_legacy_clinical_routes_are_guarded(): void
    {
        $guarded = fn (LaravelRoute $route) => array_filter(
            $route->gatherMiddleware(),
            fn ($middleware) => $middleware === 'assigned.patient' || $middleware === 'permission:patients.view_all',
        ) !== [];

        $routes = collect(Route::getRoutes()->getRoutes())->filter(fn (LaravelRoute $route) => ! str_starts_with($route->uri(), 'api/v1/'));

        foreach ($routes as $route) {
            $action = (string) $route->getActionName();

            if (str_contains($action, 'Legacy\\ClinicalController') || str_contains($action, 'Legacy\\PrescriptionController') || str_contains($action, 'Legacy\\DocumentController@')) {
                if (str_ends_with($action, '@requestMissing')) {
                    continue; // Emails staff about a document; returns no patient data.
                }
                $this->assertTrue($guarded($route), $route->uri());
            }
        }
    }
}

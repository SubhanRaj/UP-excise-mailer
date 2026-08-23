<?php

namespace Tests\Feature;

use App\Models\Designation;
use App\Models\MailAccount;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminCrudSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create(['username' => 'super1', 'role' => 'SuperAdmin']);
    }

    public function test_section_index_and_form_pages_render_and_crud_works(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin);

        $this->get(route('admin.sections.index'))->assertStatus(200)->assertSeeLivewire(\App\Livewire\Admin\SectionIndex::class);
        $this->get(route('admin.sections.create'))->assertStatus(200)->assertSeeLivewire(\App\Livewire\Admin\SectionForm::class);

        Livewire::test(\App\Livewire\Admin\SectionForm::class)
            ->set('name', 'Test Section')
            ->set('email', 'sec@example.com')
            ->call('save')
            ->assertRedirect(route('admin.sections.index'));

        $section = Section::where('name', 'Test Section')->firstOrFail();
        $this->get(route('admin.sections.edit', $section))->assertStatus(200)->assertSee('Test Section');

        Livewire::test(\App\Livewire\Admin\SectionForm::class, ['section' => $section])
            ->set('name', 'Renamed Section')
            ->call('save');
        $this->assertSame('Renamed Section', $section->fresh()->name);

        Livewire::test(\App\Livewire\Admin\SectionIndex::class)->call('delete', $section->id);
        $this->assertNull(Section::find($section->id));
    }

    public function test_designation_form_privilege_checkboxes_round_trip(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(\App\Livewire\Admin\DesignationForm::class)
            ->set('name', 'Test Designation')
            ->set('defaultPrivileges', ['campaigns.send', 'templates.manage'])
            ->call('save')
            ->assertRedirect(route('admin.designations.index'));

        $designation = Designation::where('name', 'Test Designation')->firstOrFail();
        $this->assertEqualsCanonicalizing(['campaigns.send', 'templates.manage'], $designation->default_privileges);
    }

    public function test_mail_account_form_create_and_edit_keeps_password_when_blank(): void
    {
        $this->actingAs($this->superAdmin());
        $section = Section::create(['name' => 'Sec', 'slug' => 'sec']);

        Livewire::test(\App\Livewire\Admin\MailAccountForm::class)
            ->set('sectionId', $section->id)
            ->set('gmailAddress', 'acct@example.com')
            ->set('appPassword', 'secret123')
            ->set('smtpHost', 'smtp.gmail.com')
            ->set('smtpPort', 587)
            ->set('throttleSeconds', '4')
            ->call('save')
            ->assertRedirect(route('admin.mail-accounts.index'));

        $account = MailAccount::where('gmail_address', 'acct@example.com')->firstOrFail();
        $this->assertSame('secret123', $account->app_password);

        // Blank password on edit must NOT overwrite the stored one.
        Livewire::test(\App\Livewire\Admin\MailAccountForm::class, ['mailAccount' => $account])
            ->set('throttleSeconds', '9')
            ->call('save');
        $this->assertSame('secret123', $account->fresh()->app_password);
        $this->assertSame(9, $account->fresh()->throttle_seconds);
    }

    public function test_user_form_creates_account_and_privilege_escalation_guard_holds(): void
    {
        $superAdmin = $this->superAdmin();
        $this->actingAs($superAdmin);

        Livewire::test(\App\Livewire\Admin\UserForm::class)
            ->set('name', 'New Officer')
            ->set('email', 'officer@example.com')
            ->set('role', 'User')
            ->call('save')
            ->assertRedirect(route('admin.users.index'));

        $newUser = User::where('email', 'officer@example.com')->firstOrFail();
        $this->assertSame('User', $newUser->role);
        $this->assertNull($newUser->email_verified_at);

        // A non-admin privilege-only actor must not be able to grant SuperAdmin or a privilege
        // they don't themselves hold (same guard as the old FormRequest classes).
        $limitedAdmin = User::factory()->create([
            'username' => 'limited1', 'role' => 'Admin', 'privileges' => ['users.manage', 'campaigns.send'],
        ]);
        $this->actingAs($limitedAdmin);

        Livewire::test(\App\Livewire\Admin\UserForm::class)
            ->set('name', 'Escalation Attempt')
            ->set('email', 'escalate@example.com')
            ->set('role', 'SuperAdmin')
            ->call('save')
            ->assertHasErrors('role');

        Livewire::test(\App\Livewire\Admin\UserForm::class)
            ->set('name', 'Priv Escalation')
            ->set('email', 'priv@example.com')
            ->set('role', 'User')
            ->set('privileges', ['sections.manage'])
            ->call('save')
            ->assertHasErrors('privileges.0');
    }

    public function test_all_four_index_and_create_pages_render_for_an_authenticated_admin(): void
    {
        $this->actingAs($this->superAdmin());

        foreach ([
            ['admin.sections.index', \App\Livewire\Admin\SectionIndex::class],
            ['admin.sections.create', \App\Livewire\Admin\SectionForm::class],
            ['admin.mail-accounts.index', \App\Livewire\Admin\MailAccountIndex::class],
            ['admin.mail-accounts.create', \App\Livewire\Admin\MailAccountForm::class],
            ['admin.designations.index', \App\Livewire\Admin\DesignationIndex::class],
            ['admin.designations.create', \App\Livewire\Admin\DesignationForm::class],
            ['admin.users.index', \App\Livewire\Admin\UserIndex::class],
            ['admin.users.create', \App\Livewire\Admin\UserForm::class],
        ] as [$routeName, $componentClass]) {
            $this->get(route($routeName))->assertStatus(200)->assertSeeLivewire($componentClass);
        }
    }
}

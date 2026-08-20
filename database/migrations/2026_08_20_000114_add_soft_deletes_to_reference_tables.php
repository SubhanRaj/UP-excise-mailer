<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Matches ~/Sites/excise-budget-tracker's convention: "master data"/record tables a user
     * creates and picks from (BudgetHead, Scheme, Letter, Designation, User there) get soft
     * deletes; pure join/detail/log tables (CampaignRecipient, RecipientListItem,
     * ActivityLog, and the fixed Zone/Division/District directory) don't.
     */
    public function up(): void
    {
        foreach (['sections', 'mail_accounts', 'mail_templates', 'campaigns', 'recipient_lists'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach (['sections', 'mail_accounts', 'mail_templates', 'campaigns', 'recipient_lists'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};

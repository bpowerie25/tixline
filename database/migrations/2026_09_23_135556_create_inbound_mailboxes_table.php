<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbound_mailboxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('imap_host');
            $table->unsignedSmallInteger('imap_port')->default(993);
            $table->string('imap_encryption')->nullable()->default('ssl');
            $table->string('imap_username');
            $table->text('imap_password')->nullable();
            $table->string('imap_folder')->default('INBOX');
            $table->unsignedSmallInteger('poll_interval')->default(5);
            $table->boolean('delete_after_process')->default(false);
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Migrate existing IMAP data from mail_configurations
        $configs = DB::table('mail_configurations')
            ->whereNotNull('imap_host')
            ->where('imap_host', '!=', '')
            ->get();

        foreach ($configs as $config) {
            DB::table('inbound_mailboxes')->insert([
                'tenant_id' => $config->tenant_id ?? null,
                'name' => 'Primary Mailbox',
                'imap_host' => $config->imap_host,
                'imap_port' => $config->imap_port ?: 993,
                'imap_encryption' => $config->imap_encryption ?: 'ssl',
                'imap_username' => $config->imap_username ?? '',
                'imap_password' => $config->imap_password,
                'imap_folder' => $config->imap_folder ?: 'INBOX',
                'poll_interval' => $config->imap_poll_interval ?: 5,
                'delete_after_process' => $config->imap_delete_after_process ?? false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Drop IMAP columns from mail_configurations
        Schema::table('mail_configurations', function (Blueprint $table) {
            $table->dropColumn([
                'imap_host', 'imap_port', 'imap_encryption',
                'imap_username', 'imap_password', 'imap_folder',
                'imap_poll_interval', 'imap_delete_after_process',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('mail_configurations', function (Blueprint $table) {
            $table->string('imap_host')->nullable();
            $table->unsignedSmallInteger('imap_port')->nullable();
            $table->string('imap_encryption')->nullable();
            $table->string('imap_username')->nullable();
            $table->text('imap_password')->nullable();
            $table->string('imap_folder')->default('INBOX');
            $table->unsignedSmallInteger('imap_poll_interval')->default(5);
            $table->boolean('imap_delete_after_process')->default(false);
        });

        // Migrate data back
        $mailboxes = DB::table('inbound_mailboxes')->get();
        foreach ($mailboxes as $mailbox) {
            DB::table('mail_configurations')
                ->where('tenant_id', $mailbox->tenant_id)
                ->update([
                    'imap_host' => $mailbox->imap_host,
                    'imap_port' => $mailbox->imap_port,
                    'imap_encryption' => $mailbox->imap_encryption,
                    'imap_username' => $mailbox->imap_username,
                    'imap_password' => $mailbox->imap_password,
                    'imap_folder' => $mailbox->imap_folder,
                    'imap_poll_interval' => $mailbox->poll_interval,
                    'imap_delete_after_process' => $mailbox->delete_after_process,
                ]);
        }

        Schema::dropIfExists('inbound_mailboxes');
    }
};

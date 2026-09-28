<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Mentoring is only for freelancers now: move old employer-track programs to the freelancer track.
     */
    public function up(): void
    {
        DB::table('mentorship_programs')->where('track', 'employer')->update(['track' => 'freelancer']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The old track cannot be restored.
    }
};

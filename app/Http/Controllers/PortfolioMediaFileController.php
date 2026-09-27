<?php

namespace App\Http\Controllers;

use App\Enums\AdminPermission;
use App\Enums\ModerationStatus;
use App\Models\PortfolioMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortfolioMediaFileController extends Controller
{
    /**
     * Stream a portfolio file from private storage. Until a moderator approves it, only the
     * freelancer and staff with the moderation permission can open it.
     */
    public function __invoke(Request $request, PortfolioMedia $portfolioMedia): StreamedResponse
    {
        $user = $request->user();
        $isOwner = $portfolioMedia->portfolioItem->freelancer_id === $user->id;
        $isModerator = $user->can(AdminPermission::ManageModeration->value);
        $isPublic = $portfolioMedia->status === ModerationStatus::Approved;

        abort_unless($isOwner || $isModerator || $isPublic, 404);
        abort_unless(Storage::disk('local')->exists($portfolioMedia->file_path), 404);

        return Storage::disk('local')->response($portfolioMedia->file_path, $portfolioMedia->original_filename, [], 'inline');
    }
}

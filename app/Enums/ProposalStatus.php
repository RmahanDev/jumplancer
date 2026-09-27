<?php

namespace App\Enums;

/**
 * Lifecycle of a freelancer proposal.
 */
enum ProposalStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Shortlisted = 'shortlisted';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
}

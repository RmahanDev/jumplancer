<?php

namespace App\Enums;

/**
 * File type of a portfolio proof upload.
 */
enum PortfolioMediaType: string
{
    case Image = 'image';
    case Pdf = 'pdf';
}

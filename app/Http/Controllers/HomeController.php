<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Show the marketplace homepage.
     *
     * Categories mirror the seed data in docs/database/build_jumplancer_db.bas.
     * Projects and freelancers are sample data until their models exist.
     */
    public function __invoke(): View
    {
        return view('index', [
            'categories' => $this->categories(),
            'latestProjects' => $this->latestProjects(),
            'topFreelancers' => $this->topFreelancers(),
        ]);
    }

    /**
     * @return array<int, array{name: string, slug: string, icon: string, subcategories: array<int, string>}>
     */
    private function categories(): array
    {
        return [
            ['name' => 'Programming & Tech', 'slug' => 'programming-tech', 'icon' => 'bi-code-slash', 'subcategories' => ['Web Development', 'Mobile Apps']],
            ['name' => 'Design & Creative', 'slug' => 'design-creative', 'icon' => 'bi-palette', 'subcategories' => ['UI/UX Design', 'Graphic Design']],
            ['name' => 'Writing & Translation', 'slug' => 'writing-translation', 'icon' => 'bi-pencil-square', 'subcategories' => ['Content Writing', 'Translation']],
            ['name' => 'Digital Marketing', 'slug' => 'digital-marketing', 'icon' => 'bi-megaphone', 'subcategories' => ['SEO', 'Social Media']],
        ];
    }

    /**
     * @return array<int, array{title: string, category: string, budget: string, skills: array<int, string>, proposals: int, posted: string}>
     */
    private function latestProjects(): array
    {
        return [
            ['title' => 'Laravel admin panel for an online shop', 'category' => 'Web Development', 'budget' => '$400 – $700', 'skills' => ['Laravel', 'MySQL', 'Bootstrap'], 'proposals' => 7, 'posted' => '2 hours ago'],
            ['title' => 'Mobile app UI for a food delivery startup', 'category' => 'UI/UX Design', 'budget' => '$250 – $500', 'skills' => ['Figma', 'Mobile UI'], 'proposals' => 12, 'posted' => '5 hours ago'],
            ['title' => 'Translate product catalog English → Persian', 'category' => 'Translation', 'budget' => '$120', 'skills' => ['English', 'Persian'], 'proposals' => 4, 'posted' => '1 day ago'],
            ['title' => 'SEO audit and keyword plan for a blog', 'category' => 'SEO', 'budget' => '$150 – $300', 'skills' => ['SEO', 'Google Analytics'], 'proposals' => 9, 'posted' => '1 day ago'],
        ];
    }

    /**
     * @return array<int, array{name: string, title: string, rating: float, completedProjects: int}>
     */
    private function topFreelancers(): array
    {
        return [
            ['name' => 'Sara Ahmadi', 'title' => 'Full-stack Laravel Developer', 'rating' => 4.9, 'completedProjects' => 38],
            ['name' => 'Reza Karimi', 'title' => 'UI/UX Designer', 'rating' => 4.8, 'completedProjects' => 27],
            ['name' => 'Neda Hosseini', 'title' => 'Translator & Content Writer', 'rating' => 5.0, 'completedProjects' => 54],
        ];
    }
}

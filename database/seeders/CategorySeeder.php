<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Skill;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Top-level fields, their sub-categories and the skills of each sub-category.
     *
     * @var array<int, array{name: string, slug: string, children: array<int, array{name: string, slug: string, skills: array<string, string>}>}>
     */
    private const TREE = [
        [
            'name' => 'Programming & Tech',
            'slug' => 'programming-tech',
            'children' => [
                ['name' => 'Web Development', 'slug' => 'web-development', 'skills' => [
                    'php' => 'PHP',
                    'laravel' => 'Laravel',
                    'wordpress' => 'WordPress',
                    'javascript' => 'JavaScript',
                    'react' => 'React',
                    'html-css' => 'HTML & CSS',
                ]],
                ['name' => 'Mobile Apps', 'slug' => 'mobile-apps', 'skills' => [
                    'flutter' => 'Flutter',
                ]],
            ],
        ],
        [
            'name' => 'Design & Creative',
            'slug' => 'design-creative',
            'children' => [
                ['name' => 'UI/UX Design', 'slug' => 'ui-ux', 'skills' => [
                    'figma' => 'Figma',
                ]],
                ['name' => 'Graphic Design', 'slug' => 'graphic-design', 'skills' => [
                    'photoshop' => 'Photoshop',
                ]],
            ],
        ],
        [
            'name' => 'Writing & Translation',
            'slug' => 'writing-translation',
            'children' => [
                ['name' => 'Content Writing', 'slug' => 'content-writing', 'skills' => [
                    'copywriting' => 'Copywriting',
                ]],
                ['name' => 'Translation', 'slug' => 'translation', 'skills' => [
                    'en-fa-translation' => 'English-Persian Translation',
                ]],
            ],
        ],
        [
            'name' => 'Digital Marketing',
            'slug' => 'digital-marketing',
            'children' => [
                ['name' => 'SEO', 'slug' => 'seo', 'skills' => [
                    'technical-seo' => 'Technical SEO',
                ]],
                ['name' => 'Social Media', 'slug' => 'social-media', 'skills' => [
                    'instagram-marketing' => 'Instagram Marketing',
                ]],
            ],
        ],
    ];

    /**
     * Seed the category tree and skills. Safe to run more than once (matched by slug).
     */
    public function run(): void
    {
        foreach (self::TREE as $parentOrder => $parentData) {
            $parent = Category::updateOrCreate(
                ['slug' => $parentData['slug']],
                ['name' => $parentData['name'], 'parent_id' => null, 'sort_order' => $parentOrder + 1],
            );

            foreach ($parentData['children'] as $childOrder => $childData) {
                $child = Category::updateOrCreate(
                    ['slug' => $childData['slug']],
                    ['name' => $childData['name'], 'parent_id' => $parent->id, 'sort_order' => $childOrder + 1],
                );

                foreach ($childData['skills'] as $slug => $name) {
                    Skill::updateOrCreate(
                        ['slug' => $slug],
                        ['name' => $name, 'category_id' => $child->id],
                    );
                }
            }
        }
    }
}

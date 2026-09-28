<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Skill;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Top-level fields, their sub-categories and the skills of each sub-category (Persian names, Latin slugs).
     *
     * @var array<int, array{name: string, slug: string, children: array<int, array{name: string, slug: string, skills: array<string, string>}>}>
     */
    private const TREE = [
        [
            'name' => 'برنامه‌نویسی و فناوری',
            'slug' => 'programming-tech',
            'children' => [
                ['name' => 'توسعه‌ی وب', 'slug' => 'web-development', 'skills' => [
                    'php' => 'PHP',
                    'laravel' => 'Laravel',
                    'wordpress' => 'WordPress',
                    'javascript' => 'JavaScript',
                    'react' => 'React',
                    'html-css' => 'HTML & CSS',
                    'mysql' => 'MySQL',
                    'bootstrap' => 'Bootstrap',
                ]],
                ['name' => 'اپلیکیشن موبایل', 'slug' => 'mobile-apps', 'skills' => [
                    'flutter' => 'Flutter',
                    'android' => 'Android',
                ]],
            ],
        ],
        [
            'name' => 'طراحی و خلاقیت',
            'slug' => 'design-creative',
            'children' => [
                ['name' => 'طراحی رابط و تجربه‌ی کاربری', 'slug' => 'ui-ux', 'skills' => [
                    'figma' => 'Figma',
                    'user-research' => 'تحقیق کاربر',
                ]],
                ['name' => 'طراحی گرافیک', 'slug' => 'graphic-design', 'skills' => [
                    'photoshop' => 'Photoshop',
                    'illustrator' => 'Illustrator',
                    'logo-design' => 'طراحی لوگو',
                ]],
            ],
        ],
        [
            'name' => 'نویسندگی و ترجمه',
            'slug' => 'writing-translation',
            'children' => [
                ['name' => 'تولید محتوا', 'slug' => 'content-writing', 'skills' => [
                    'copywriting' => 'کپی‌رایتینگ',
                    'seo-writing' => 'مقاله‌نویسی سئو',
                ]],
                ['name' => 'ترجمه', 'slug' => 'translation', 'skills' => [
                    'en-fa-translation' => 'ترجمه‌ی انگلیسی به فارسی',
                ]],
            ],
        ],
        [
            'name' => 'دیجیتال مارکتینگ',
            'slug' => 'digital-marketing',
            'children' => [
                ['name' => 'سئو', 'slug' => 'seo', 'skills' => [
                    'technical-seo' => 'سئوی تکنیکال',
                    'google-analytics' => 'Google Analytics',
                ]],
                ['name' => 'شبکه‌های اجتماعی', 'slug' => 'social-media', 'skills' => [
                    'instagram-marketing' => 'بازاریابی اینستاگرام',
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

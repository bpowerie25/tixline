<?php

namespace Database\Seeders;

use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class KbFaqSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::firstOrFail();
        $author = User::where('tenant_id', $tenant->id)->first();

        $faqs = [
            'Accreditation' => [
                [
                    'question' => 'Which courses would give me a Master\'s level qualification?',
                    'answer' => 'The following programme formats all lead to the same Joint Master\'s Degree in Advanced Digital Skills: Full-time Master\'s programme (2 semesters), Part-time accelerated Master\'s programme (3 semesters), and Part-time Master\'s programme (4 semesters). All three pathways require students to complete 60 ECTS. Postgraduate pathways and individual microcredentials do not lead to the full Master\'s qualification on their own.',
                ],
                [
                    'question' => 'Is Digital4Business an officially recognised Master\'s degree?',
                    'answer' => 'Yes. The Digital4Business Joint Master\'s Degree in Advanced Digital Skills is an officially recognised Master\'s level qualification. It is a 60 ECTS, EQF Level 7 programme. The degree is accredited through ASIIN, a recognised European accreditation body, meaning that it is an officially recognised Master\'s qualification across Europe. In addition, the degree is also individually accredited through NOVA University Lisbon and the University of Bologna.',
                ],
                [
                    'question' => 'Which institution issues the diploma after graduation?',
                    'answer' => 'Upon successful completion, students receive the Joint Master\'s Degree in Advanced Digital Skills and a Diploma Supplement. The Joint Degree is awarded by NOVA University Lisbon and the University of Bologna. Students who successfully complete the programme will be awarded a Joint Professional Master\'s degree in Advanced Digital Technologies for Business by these degree-awarding institutions, which jointly issue, register, and deliver a single diploma and diploma supplement detailing the academic programme and achievements in line with European Commission standards.',
                ],
                [
                    'question' => 'What is the end result of successful completion of this course?',
                    'answer' => 'If you successfully complete a full Digital4Business Master\'s pathway, you will be awarded the Joint Master\'s Degree in Advanced Digital Skills, a 60 ECTS, EQF Level 7 qualification. Students who complete only part of the programme may instead receive: a postgraduate qualification worth 30 ECTS (not a full Master\'s degree) or individual accredited microcredentials, normally worth 10 ECTS each. Microcredentials do not lead to a Master\'s degree unless later combined within a full programme pathway.',
                ],
            ],
            'Administration' => [
                [
                    'question' => 'How do I withdraw from the course?',
                    'answer' => 'Contact the admissions or programme administration team in writing to initiate withdrawal. They will explain the formal process and address implications regarding fees, system access, and future re-entry possibilities.',
                ],
                [
                    'question' => 'How do I defer my place on the course?',
                    'answer' => 'Contact the admissions team promptly to request deferral to a later intake. Individual consideration applies based on timing and availability. Review the published timetable to verify completion feasibility before deferring, ensuring you can finish all requirements and obtain credits by the final semester deadline.',
                ],
                [
                    'question' => 'What is the policy on refunds?',
                    'answer' => 'Refund policies vary by programme, withdrawal timing, and payment status. Contact the admissions or programme administration team directly for current information on refunds and applicable conditions.',
                ],
                [
                    'question' => 'Can I switch from one programme to another during the academic year?',
                    'answer' => 'Programme switches are evaluated case-by-case, considering current progress, request timing, module availability, and programme completion feasibility. Contact Programme Coordinators early to discuss. Verify timetable details beforehand to confirm you can complete all requirements and obtain necessary credits by the final semester.',
                ],
            ],
            'Application and Admissions' => [
                [
                    'question' => 'Where can I find information about fees?',
                    'answer' => 'Detailed fee information, payment options, and available discounts are located on the Fees & Payment Plans page. It includes current costs for each study format such as Master\'s pathways and microcredentials, plus flexible payment plan details.',
                ],
                [
                    'question' => 'What happens if I am not eligible to take the course?',
                    'answer' => 'If ineligible, you may receive feedback on unmet criteria. Applicants can strengthen their profile through experience or prerequisites and reapply later. The admissions team offers guidance on alternative pathways like microcredentials or preparatory options.',
                ],
                [
                    'question' => 'How do I select the modules I want to study?',
                    'answer' => 'After admission, you\'ll choose modules or learning pathways in your personal D4B student profile. Each format has its own structure: full Master\'s pathways include core and elective modules, while microcredentials are standalone options.',
                ],
                [
                    'question' => 'How do I apply for a course?',
                    'answer' => 'Start by completing the eligibility check via the Digital4Business portal. Upon confirmation of basic criteria, you\'ll receive instructions for formal application submission with supporting documents, followed by fee payment and enrollment.',
                ],
                [
                    'question' => 'What is the eligibility check form?',
                    'answer' => 'The eligibility check form is the initial step confirming whether you meet basic admission conditions. It covers academic background, professional experience, and specific requirements for your chosen pathway like Master\'s or microcredentials.',
                ],
            ],
            'Course Content and Delivery' => [
                [
                    'question' => 'Where can I find the academic calendar?',
                    'answer' => 'The academic calendar is available on the master\'s web page, the master\'s platform, and the Moodle platform at https://digital4business.eu/our-programme/timetable/',
                ],
                [
                    'question' => 'How do I use my student email?',
                    'answer' => 'The D4B email address provided after official enrollment is used to attend classes on Teams, access Moodle, and communicate with lecturers through Moodle.',
                ],
                [
                    'question' => 'How are Digital4Business modules assessed?',
                    'answer' => 'Assessment aligns with module learning outcomes through exams designed to measure objective achievement. Students receive assessment information at module start, with graders providing general feedback within two weeks. Methods include assignments with rubrics and are regularly reviewed for quality.',
                ],
                [
                    'question' => 'Can I audit a module?',
                    'answer' => 'No, official enrollment is required to attend classes and access course materials. Auditing is not permitted.',
                ],
                [
                    'question' => 'How do I access the online library?',
                    'answer' => 'Participants are enrolled in a joint master\'s program funded by the European Union across four institutions, not as traditional university students, so standard university login credentials are not available.',
                ],
                [
                    'question' => 'Where can I find my class timetable?',
                    'answer' => 'Class schedules are available in the Moodle calendar and Teams calendar, which includes class times and session join links.',
                ],
                [
                    'question' => 'What online platform will be used to host Digital4Business programmes?',
                    'answer' => 'The program uses three platforms: the master\'s platform (https://my.digital4business.eu/), Moodle for academic content, and Teams for live synchronous classes.',
                ],
                [
                    'question' => 'Will all lectures be recorded?',
                    'answer' => 'Recording availability varies by module based on content suitability for asynchronous learning. Students should ask during the first lesson whether sessions will be recorded and made available on the platform.',
                ],
            ],
            'Data Protection and Privacy' => [
                [
                    'question' => 'Can I request that my email address be deleted from the system?',
                    'answer' => 'Yes, you may request removal of your email address. However, while actively studying, the programme must retain a valid email for Moodle access, Teams, communications, and assessments. If requesting deletion during studies, provide an alternative email. After completing or withdrawing, you can request deletion subject to legal and record-keeping requirements.',
                ],
                [
                    'question' => 'Can I request that my registration and enrolment information be kept confidential and that my data be deleted after I complete my studies?',
                    'answer' => 'Digital4Business complies with GDPR and data protection legislation. You may request confidentiality and can request access, correction, or deletion of personal data. However, enrolment, progression, and award information may require retention to meet legal, regulatory, and academic requirements. After retention periods expire, data deletion is typically available upon request. Contact programme administration for data protection requests.',
                ],
            ],
            'Future Intakes' => [
                [
                    'question' => 'Will the course be available for non-EU nationals in the future?',
                    'answer' => 'To check for changes in the admission criteria, visit the website: https://digital4business.eu/check-your-eligibility/',
                ],
                [
                    'question' => 'When is the next intake of students planned?',
                    'answer' => 'The latest edition of the master\'s program is scheduled for September 2026. More details: https://digital4business.eu/our-programme/timetable/',
                ],
            ],
            'Official Documentation' => [
                [
                    'question' => 'Am I eligible for student benefits, such as student discounts or a student card?',
                    'answer' => 'At present, Digital4Business students are not eligible for standard university student cards or student discount schemes.',
                ],
                [
                    'question' => 'How can I request proof that I am a student on a Digital4Business programme?',
                    'answer' => 'If you need proof of enrollment, contact the admissions or programme administration team. They can provide an official confirmation letter or other supporting documentation.',
                ],
            ],
        ];

        foreach ($faqs as $categoryName => $articles) {
            $category = KbCategory::updateOrCreate(
                ['slug' => Str::slug($categoryName)],
                [
                    'tenant_id' => $tenant->id,
                    'name' => $categoryName,
                    'description' => "Frequently asked questions about {$categoryName}",
                    'sort_order' => 0,
                ]
            );

            foreach ($articles as $index => $article) {
                KbArticle::updateOrCreate(
                    ['slug' => Str::slug($article['question'])],
                    [
                        'tenant_id' => $tenant->id,
                        'category_id' => $category->id,
                        'author_id' => $author?->id,
                        'title' => $article['question'],
                        'excerpt' => Str::limit($article['answer'], 150),
                        'body' => '<p>' . e($article['answer']) . '</p>',
                        'status' => 'published',
                        'sort_order' => $index,
                        'published_at' => now(),
                    ]
                );
            }
        }
    }
}

<?php

/*
|--------------------------------------------------------------------------
| Frontend module
|--------------------------------------------------------------------------
| 'defaults' is the starting content of every business website. A business
| only stores what it changed (frontend_sites.content); the rest falls back
| to these values. ":company" is replaced with the business name.
|
| Lists (features, services, legal sections) are replaced as a whole when a
| business saves them, so removed items stay removed.
*/

return [
    'name' => 'Frontend',

    'defaults' => [

        'general' => [
            'seo_title' => ':company',
            'seo_description' => 'Welcome to :company.',
            // Customer accounts are off until the business turns them on.
            'allow_login' => false,
            'allow_registration' => false,
        ],

        'contact' => [
            'email' => '',
            'phone' => '',
            'address' => '',
        ],

        'header' => [
            'show_about' => true,
            'show_services' => true,
            'show_contact' => true,
            'show_account' => true,
        ],

        'footer' => [
            'blurb' => ':company — simple, secure and here to help.',
            'social' => [
                'facebook' => '',
                'instagram' => '',
                'x' => '',
                'linkedin' => '',
                'whatsapp' => '',
            ],
        ],

        'home' => [
            'hero' => [
                'show' => true,
                'tagline' => 'Welcome to :company',
                'title' => 'Everything you need, in one place.',
                'subtitle' => 'Create an account to get started, or sign in to pick up where you left off.',
                'primary_label' => 'Get started',
                'secondary_label' => 'Log in',
            ],
            'features' => [
                'show' => true,
                'tagline' => 'Why :company',
                'title' => 'Built to be simple',
                'description' => ':company keeps things clear, secure and easy to use, with support when you need it.',
                'items' => [
                    ['title' => 'Reliable', 'description' => 'Dependable service you can count on, every time.'],
                    ['title' => 'Secure', 'description' => 'Your account and data are protected by modern security.'],
                    ['title' => 'Easy to use', 'description' => 'Sign up in a minute and find what you need fast.'],
                    ['title' => 'Support', 'description' => 'Real people ready to help when you need it.'],
                ],
            ],
            'about' => [
                'show' => true,
                'tagline' => 'About :company',
                'title' => 'Who we are',
                'description' => ':company is built around the people it serves: dependable service, clear communication, and support every step of the way.',
            ],
            'cta' => [
                'show' => true,
                'title' => 'Ready to get started?',
                'subtitle' => 'Create your account in under a minute.',
                'button' => 'Get started',
            ],
            'contact' => [
                'show' => true,
                'tagline' => 'Contact',
                'title' => 'Get in touch',
                'lead' => 'Questions, feedback or partnerships? Reach out and we will get back to you.',
            ],
        ],

        'pages' => [
            'about' => [
                'tagline' => 'About :company',
                'title' => 'Who we are',
                'body' => ":company is built around the people it serves.\n\nWe focus on dependable service, clear communication, and support every step of the way.",
            ],
            'services' => [
                'tagline' => 'What we offer',
                'title' => 'Our services',
                'items' => [
                    ['title' => 'Service one', 'description' => 'Describe your first service here.'],
                    ['title' => 'Service two', 'description' => 'Describe your second service here.'],
                    ['title' => 'Service three', 'description' => 'Describe your third service here.'],
                ],
            ],
            'contact' => [
                'tagline' => 'Contact',
                'title' => 'Talk to :company',
                'lead' => 'Send us a message using the details below and we will reply as soon as we can.',
            ],
            'privacy' => [
                'title' => 'Privacy Policy',
                'updated' => '',
                'sections' => [
                    ['title' => 'Who we are', 'body' => ':company (“we”, “us”) operates this website. This policy explains how we collect and use personal information when you browse the site or use your account.'],
                    ['title' => 'What we collect', 'body' => 'We collect the account details you give us (name, email, phone) and the records needed to provide our services to you.'],
                    ['title' => 'How we use it', 'body' => 'We use your information to run your account, provide our services, send service updates and meet legal requirements.'],
                    ['title' => 'Sharing', 'body' => 'We only share necessary details with staff and partners who help us serve you. We do not sell your personal information.'],
                    ['title' => 'Your rights', 'body' => 'You can ask :company to access, correct or delete your personal information at any time.'],
                ],
            ],
            'terms' => [
                'title' => 'Terms & Conditions',
                'updated' => '',
                'sections' => [
                    ['title' => 'Agreement', 'body' => 'By creating an account on :company, you agree to these Terms & Conditions.'],
                    ['title' => 'Your account', 'body' => 'Keep your sign-in details private and your information accurate. You are responsible for activity on your account.'],
                    ['title' => 'Acceptable use', 'body' => 'Do not misuse the website, attempt to access accounts or data that are not yours, or interfere with its operation.'],
                    ['title' => 'Liability', 'body' => ':company is not liable for indirect losses beyond what applicable law requires.'],
                ],
            ],
        ],

    ],
];

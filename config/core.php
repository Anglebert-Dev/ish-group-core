<?php

return [

    // Version of the core. Raise it (semantic: 1.0.1, 1.1.0, …) before
    // publishing a core update: Self-hosting → Releases → Publish core update.
    // Customers' servers compare it to decide what to install.
    'version' => '1.0.0',

    // The git commit a zip or core package was built from (written by the build).
    'build' => null,

    // What kind of organisation each business is (Business → type). Labels: body.business_type_<key>.
    'business_types' => [
        'school', 'university', 'bus_owner', 'hospital', 'clinic', 'hotel', 'restaurant',
        'retail_shop', 'market', 'sacco', 'cooperative', 'ngo', 'church', 'utility',
        // Public & government
        'ministry', 'local_government', 'embassy', 'law_enforcement', 'military', 'state_owned_enterprise', 'civil_society',
        // Education & health
        'daycare', 'tvet_college', 'research_institute', 'library', 'pharmacy', 'diagnostic_center', 'rehab_center', 'veterinary_clinic',
        // Financial & legal
        'commercial_bank', 'microfinance', 'insurance_company', 'fintech', 'law_firm',
        // Transport & infrastructure
        'logistics_company', 'airline', 'railway', 'fuel_station',
        // Commerce & industry
        'wholesaler', 'e_commerce', 'factory',
        // Hospitality, sports & faith
        'bar_nightclub', 'travel_agency', 'gym_fitness', 'theater_cinema', 'mosque', 'temple', 'community_center',
        // Corporate services
        'consultancy', 'real_estate', 'media_house', 'security_firm',
        'other',
    ],

];

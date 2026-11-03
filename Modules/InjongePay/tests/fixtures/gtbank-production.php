<?php

/*
 * Real GTBank PaymentBridge answers, copied from the production log of
 * 2026-11-03 (storage/logs/gtbank-*.log). Names and numbers as GTBank sent them.
 */
return [
    'token' => ['expires_in' => 86400, 'access_token' => 'x', 'token_type' => 'Bearer'],

    'merchant_update_pending' => ['merchantId' => '55500004', 'message' => 'Merchant update request accepted and is being processed', 'status' => 'PENDING'],

    'merchant_lookup_active' => ['responseCode' => '00', 'responseMessage' => 'Merchant found successfully.', 'data' => ['participantName' => 'GTBANK', 'participantCode' => '070', 'merchant' => ['merchantId' => '55500004', 'businessName' => 'Inside Afrika ltd', 'businessOwnerName' => 'Inside Afrika ltd', 'status' => 'ACTIVE']]],

    'lookup_wallet' => ['names' => 'Rose Mary MUSABYIMANA', 'account' => '0788811607', 'msisdn' => '0788811607', 'email' => '', 'bank' => ['code' => '078', 'name' => 'MTN']],

    'lookup_bank_account' => ['names' => 'BAGARAGAZA MAURICE', 'account' => '01705070002', 'msisdn' => '0783599370', 'email' => 'bagaragaza@gmail.com', 'bank' => ['code' => '076', 'name' => null]],

    // HTTP 500: "+250788811607" or an unknown account number ("572308").
    'lookup_unreadable' => ['error' => 'Unable to complete lookup: Lookup failed: Error while extracting response for type [class com.example.mpayhub.dto.DigiPayLookupResponse] and content type [application/json]'],

    // HTTP 500: DigiPay refused GTBank's own login; the transfer was never created (status later: not found).
    'transfer_downstream_401' => ['error' => 'Disbursement initiation failed: 401  on POST request for "https://api.gtbank.rw/digipay/api/v2/digipay/pay": [no body]'],

    // HTTP 500 wrapping DigiPay's 400.
    'status_not_found' => ['error' => 'Unable to retrieve disbursement status: Status check failed: 400  on POST request for "https://api.gtbank.rw/digipay/api/v2/digipay/check-status": "{"error":"Transaction not found for reference: CSH-20261103194452-6804","status":"FAILED","timestamp":"2026-11-03T17:45:37.104357517"}"'],

    // After a 15 s timeout on /disbursement/transfer, 30 s later:
    'status_acsc_wallet' => ['status' => 'ACSC', 'document' => 'M00026711', 'request_id' => 'CSH-20261103202003-5202', 'bank_code' => '078', 'bank_name' => 'MTN MOBILE MONEY RWANDA LTD', 'receiver_id' => '0788811607', 'sender_id' => '2270038561', 'cbs_transaction_reference' => 'M00026711', 'receiver_names' => 'Rose Mary MUSABYIMANA', 'created_at' => '2026-11-03T18:20:04.253982'],

    'status_acsc_bank' => ['status' => 'ACSC', 'document' => 'M00036252', 'request_id' => 'CSH-20261019143510-7374', 'bank_code' => '044', 'bank_name' => 'BANQUE POPULAIRE DU RWANDA', 'receiver_id' => '4491159394', 'sender_id' => '2270038561', 'cbs_transaction_reference' => 'M00036252', 'receiver_names' => 'INSIDE AFRIKA LTD', 'created_at' => '2026-10-19T12:35:12.157102'],

    // GTBank's webhook source address in production; it sends no secret header.
    'webhook_ip' => '10.22.198.46',
];

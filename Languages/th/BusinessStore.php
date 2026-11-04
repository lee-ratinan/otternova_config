<?php
return [
    'fields' => [
        'store_name'             => 'ชื่อร้านค้า',
        'store_type'             => 'ประเภทร้านค้า',
        'store_phone_number'     => 'หมายเลขโทรศัพท์',
        'store_address_line_1'   => 'ที่อยู่บรรทัดที่ 1',
        'store_address_line_2'   => 'ที่อยู่บรรทัดที่ 2',
        'store_city'             => 'เมือง / อำเภอ',
        'store_subdivision_code' => 'จังหวัด / รัฐ',
        'store_postal_code'      => 'รหัสไปรษณีย์',
    ],
    'enum'   => [
        'store_type' => ['P' => 'หน้าร้าน', 'O' => 'ร้านค้าออนไลน์'],
    ],
];

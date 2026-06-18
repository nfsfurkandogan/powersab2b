<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $dealer = Dealer::query()->firstOrCreate(
            ['code' => 'DLR-001'],
            ['name' => 'Powersa Demo Dealer', 'is_active' => true]
        );

        $customers = [
            ['code' => '130-00-000', 'name' => 'BATUM DEPO (SIPARIS)', 'city' => 'BATUMI', 'district' => 'BATUMI', 'phone' => null],
            ['code' => '130-04-011', 'name' => 'SONTURLAR YAG FILTRE DEGISIMI SERVISI MURAT', 'city' => 'AGRI', 'district' => 'AGRI MERKEZ', 'phone' => '05392422909'],
            ['code' => '130-04-014', 'name' => 'VEFA OTOMOTIV FECRI BABAN', 'city' => 'AGRI', 'district' => 'AGRI MERKEZ', 'phone' => '05317424964'],
            ['code' => '130-04-019', 'name' => 'ERDEM OTOMOTIV ABDULKADIR ERDEM', 'city' => 'AGRI', 'district' => 'AGRI MERKEZ', 'phone' => '05448149004'],
            ['code' => '130-04-022', 'name' => 'OTO DENIZ TAYFUN ALTINDAG', 'city' => 'AGRI', 'district' => 'AGRI MERKEZ', 'phone' => '05306920396'],
            ['code' => '130-24-035', 'name' => 'SONMEZLER OTOMOTIV SAN. TIC. HARUN AYDEMIR', 'city' => 'ERZINCAN', 'district' => 'ERZINCAN MERKEZ', 'phone' => '05325616221'],
            ['code' => '130-24-059', 'name' => 'EMRE NAS INS. SAN. VE TIC. LTD. STI.', 'city' => 'ERZINCAN', 'district' => 'ERZINCAN MERKEZ', 'phone' => '05372891253'],
            ['code' => '130-25-069', 'name' => 'SAHINLER OTO YAG MARKET ADEM SAHIN', 'city' => 'ERZURUM', 'district' => 'ERZURUM MERKEZ', 'phone' => '05306640416'],
            ['code' => '130-25-153', 'name' => 'UGUR OTO ELEKTRIK OSMAN NURI KOSE', 'city' => 'ERZURUM', 'district' => 'HORASAN', 'phone' => '05327969473'],
            ['code' => '130-25-168', 'name' => 'OTO PALANDOKEN M. SEFA BINGOL', 'city' => 'ERZURUM', 'district' => 'AZIZIYE', 'phone' => '05053833129'],
            ['code' => '130-25-171', 'name' => 'OTO ULASTIRMA MEHMET ALI BINGOL', 'city' => 'ERZURUM', 'district' => 'ASKALE', 'phone' => '05323837617'],
            ['code' => '130-25-282', 'name' => 'OTO GENCLER SALIH KARAKULLUKCU', 'city' => 'ERZURUM', 'district' => 'HORASAN', 'phone' => '05323007477'],
            ['code' => '130-28-006', 'name' => 'UMIT OTO MOTOR MEK. TAM. UMIT CALIK', 'city' => 'GIRESUN', 'district' => 'GORELE', 'phone' => '05452330689'],
            ['code' => '130-28-018', 'name' => 'OZKUL OTOMOTIV INS.GID.TEKS.SAN.TIC.LTD.STI.', 'city' => 'GIRESUN', 'district' => 'ESPIYE', 'phone' => '05324618083'],
            ['code' => '130-28-024', 'name' => 'CAGNUR BOYA VE OTO YEDEK PARCA', 'city' => 'GIRESUN', 'district' => 'GIRESUN MERKEZ', 'phone' => '05365805859'],
            ['code' => '130-28-037', 'name' => 'PEKDEMIR MOTOR YEDEK PARCA RIFKI PEKDEMIR', 'city' => 'GIRESUN', 'district' => 'BULANCAK', 'phone' => '05325945030'],
            ['code' => '130-28-040', 'name' => 'MUTLU CELIK', 'city' => 'GIRESUN', 'district' => 'BULANCAK', 'phone' => '05343895877'],
            ['code' => '130-28-044', 'name' => 'YETIS TICARET NAIL EVRAN', 'city' => 'GIRESUN', 'district' => 'PIRAZIZ', 'phone' => '05327021277'],
            ['code' => '130-28-053', 'name' => 'CICEK OTO YIKAMA BEYTULLAH CAYAN', 'city' => 'GIRESUN', 'district' => 'YAGLIDERE', 'phone' => '05342669338'],
        ];

        $salespersonId = User::query()
            ->where('email', 'salesperson@powersa.test')
            ->value('id');

        Customer::query()
            ->where('dealer_id', $dealer->id)
            ->where('code', 'like', 'CR-%')
            ->delete();

        foreach ($customers as $index => $customer) {
            Customer::query()->updateOrCreate(
                [
                    'dealer_id' => $dealer->id,
                    'code' => $customer['code'],
                ],
                [
                    'salesperson_user_id' => $salespersonId,
                    'name' => $customer['name'],
                    'contact_name' => $customer['name'],
                    'phone' => $customer['phone'],
                    'city' => $customer['city'],
                    'district' => $customer['district'],
                    'tax_office' => $customer['city'],
                    'tax_number' => sprintf('9%09d', $index + 1),
                    'credit_limit' => 150000,
                    'is_active' => true,
                    'meta' => [
                        'source' => 'customer-list-import',
                    ],
                ]
            );
        }
    }
}

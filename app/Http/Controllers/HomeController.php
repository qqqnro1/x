<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;
use App\Models\ChanLe;
use App\Models\TaiXiu;
use App\Models\ChanLe2;
use App\Models\Gap3;
use App\Models\Tong3So;
use App\Models\X1Phan3;
use App\Models\AccountMomo;
use App\Models\LichSuChoiMomo;
use App\Models\SettingPhanThuongTop;
use Illuminate\Support\Carbon;
use App\Models\NoHuu;
use App\Models\LichSuChoiNoHu;
use App\Models\LichSuBank;

class HomeController extends Controller
{

    //index
    public function index(){
        $AccountMomo = new AccountMomo;

        //Setting
        $Setting = new Setting;
        $GetSetting = $Setting->first();
        $GetSetting->namepage = 'Trang chủ';
        
        //Bảo trì
        if ($GetSetting->baotri == 1){
            return;
        }

        //Chẵn lẻ
        $ChanLe = new ChanLe;
        $Setting_ChanLe = $ChanLe->first();
        $Setting_ChanLe->sdt2 = $AccountMomo->GetListAccountID($Setting_ChanLe->sdt);

        $Setting_ChanLe = $Setting_ChanLe->toArray();

        //Tài xỉu
        $TaiXiu = new TaiXiu;
        $Setting_TaiXiu = $TaiXiu->first();
        $Setting_TaiXiu->sdt2 = $AccountMomo->GetListAccountID($Setting_TaiXiu->sdt);
        
        $Setting_TaiXiu = $Setting_TaiXiu->toArray();

        //Chẵn lẻ 2
        $ChanLe2 = new ChanLe2;
        $Setting_ChanLe2 = $ChanLe2->first();
        $Setting_ChanLe2->sdt2 = $AccountMomo->GetListAccountID($Setting_ChanLe2->sdt);

        $Setting_ChanLe2 = $Setting_ChanLe2->toArray();


        //Gấp 3
        $Gap3 = new Gap3;
        $Setting_Gap3 = $Gap3->first();
        $Setting_Gap3->sdt2 = $AccountMomo->GetListAccountID($Setting_Gap3->sdt);

        $Setting_Gap3 = $Setting_Gap3->toArray();

        //Tổng 3 Số
        $Tong3So = new Tong3So;
        $Setting_Tong3So = $Tong3So->first();
        $Setting_Tong3So->sdt2 = $AccountMomo->GetListAccountID($Setting_Tong3So->sdt);

        $Setting_Tong3So = $Setting_Tong3So->toArray();

        //1 Phần 3
        $X1Phan3 = new X1Phan3;
        $Setting_1Phan3 = $X1Phan3->first();
        $Setting_1Phan3->sdt2 = $AccountMomo->GetListAccountID($Setting_1Phan3->sdt);

        $Setting_1Phan3 = $Setting_1Phan3->toArray();

        //Trạng thái MOMO
        $ListAccount = $AccountMomo->get();

        $ListAccounts = [];
        $dem = 0;

   
         foreach($ListAccount as $row){
            $ListAccounts[$dem] = $row;
            $ListAccounts[$dem]->status_text = $AccountMomo->TextStatus($row->status);
            $ListAccounts[$dem]->status_class = $AccountMomo->ClassStatus($row->status);

            $ListAccounts[$dem]->limit1 = 0;
            $LichSuChoiMomo = new LichSuChoiMomo;
            $GetLichSuChoiMomo = $LichSuChoiMomo->whereDate('created_at', Carbon::today())->where([
                'sdt_get' => $row->sdt,
            ])->where('status', '!=', 5)->get();
        
            foreach($GetLichSuChoiMomo as $res){
                $ListAccounts[$dem]->limit1 = $ListAccounts[$dem]->limit1 + $res->tiennhan;
            }

            $ListAccounts[$dem]->limit2 = $row->gioihan;
            $ListAccounts[$dem]->countbank = 0;

            //Lấy số lần bank
            $LichSuBank = new LichSuBank;
            $countbank = 0;
            $getLichSuBank = $LichSuBank->whereDate('created_at', Carbon::today())->where([
                'sdtbank' => $row->sdt
            ])->get();
    
            foreach($getLichSuBank as $r) {
                $j = json_decode($r->response, true);
    
                if( isset($j['status']) && $j['status'] == 200 ) {
                    $countbank ++;
                }
            }

            //echo $countbank; exit;
            $ListAccounts[$dem]->countbank = $countbank;

            $dem++;
        }

        //Lịch sử chơi Momo
        $LichSuChoiMomo = new LichSuChoiMomo;
        $ListLichSuChoiMomo = $LichSuChoiMomo->orderBy('id', 'desc')->get();
        $LichSuGiaoDich = [];
        $dem = 0;

        foreach($ListLichSuChoiMomo as $row){
            if ($dem < 10) {
                if ($row['status'] == 3) {
                    if ( $row['ketqua'] == 1 || $row['ketqua'] == 2 || $row['ketqua'] == 3) {
                        $LichSuGiaoDich[$dem] = $row;
                        $LichSuGiaoDich[$dem]['sdt2'] = substr($row->sdt, 0, 6).'******';
                        $LichSuGiaoDich[$dem]['class'] = 'success';
                        $LichSuGiaoDich[$dem]['text'] = 'Thắng';
                        $dem ++;
                    }
                }
            }
        }


        //Thuật toán tìm TOP tuần
        $TopTuan = [];
        $dem = 0;

        $ListSDT = [];
        $st = 0;

        $now = Carbon::now();
        $weekStartDate = $now->startOfWeek()->format('Y-m-d H:i');
        $weekEndDate = $now->endOfWeek()->format('Y-m-d H:i');

        $ListLichSuChoiMomo = $LichSuChoiMomo->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])->get();

        foreach ($ListLichSuChoiMomo as $row) {
            $sdt = $row->sdt;

            $check = True;
            foreach ($ListSDT as $res) {
                if ($res == $sdt) {
                    $check = False;
                }
            }

            if ($check) {
                $ListSDT[$st] = $sdt;
                $st ++;
            }
            
        }

        $ListUser = [];
        $dem = 0;

        foreach($ListSDT as $row){
            $Result = $LichSuChoiMomo->where([
                'sdt' => $row,
                'status' => 3,
            ])->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])->get();

            $ListUser[$dem]['sdt'] = $row;
            $ListUser[$dem]['tiencuoc'] = 0;

            foreach ($Result as $res) {
                $ListUser[$dem]['tiencuoc'] = $ListUser[$dem]['tiencuoc'] + $res->tiencuoc;
            }

            $dem ++;
        }

        $UserTop = [];
        $st = 0;

        if ($dem > 1) {
            // Đếm tổng số phần tử của mảng
            $sophantu = count($ListUser);
            // Lặp để sắp xếp
            for ($i = 0; $i < $sophantu - 1; $i++)
            {
                // Tìm vị trí phần tử lớn nhất
                $max = $i;
                for ($j = $i + 1; $j < $sophantu; $j++){
                    if ($ListUser[$j]['tiencuoc'] > $ListUser[$max]['tiencuoc']){
                        $max = $j;
                    }
                }
                // Sau khi có vị trí lớn nhất thì hoán vị
                // với vị trí thứ $i
                $temp = $ListUser[$i];
                $ListUser[$i] = $ListUser[$max];
                $ListUser[$max] = $temp;
            }

            $UserTop = $ListUser;
        } else {
            $UserTop = $ListUser;
        }

        $UserTopTuan = [];
        $dem = 0;

        foreach ($UserTop as $row) {
            if ( $dem < 5 ) {
                $UserTopTuan[$dem] = $row;
                $UserTopTuan[$dem]['sdt2'] = substr($row['sdt'], 0, 6).'******';
                $dem ++;
            }
        }

        //Phần thưởng tuần
        $SettingPhanThuongTop = new SettingPhanThuongTop;
        $GetSettingPhanThuongTop = $SettingPhanThuongTop->get();

        //Setting nổ hũ
        $NoHuu = new NoHuu;
        $Setting_NoHu = $NoHuu->first();

        //Thông báo nổ hũ
        $LichSuChoiNoHu = new LichSuChoiNoHu;
        $GetLichSuChoiNoHu = $LichSuChoiNoHu->where([
            'status' => 3,
            'ketqua' => 1,
        ])->get();

        $GetLichSuChoiNoHus = [];
        $dem = 0;

        foreach ( $GetLichSuChoiNoHu as $row ) {
            $GetLichSuChoiNoHus[$dem] = $row;
            $GetLichSuChoiNoHus[$dem]['sdt2'] = substr($row['sdt'], 0, 6).'******';
            $GetLichSuChoiNoHus[$dem]['tiennhan2'] = $row['tiennhan'] + $Setting_NoHu->tienmacdinh;
            $dem ++;
        }


        //View
        return view(
            'HomePage.home',
            compact(
                'GetSetting',
                'Setting_ChanLe',
                'Setting_TaiXiu',
                'Setting_ChanLe2',
                'Setting_Gap3',
                'Setting_Tong3So',
                'Setting_1Phan3',
                'ListAccounts',
                'LichSuGiaoDich',
                'UserTopTuan',
                'GetSettingPhanThuongTop',
                'GetLichSuChoiNoHus'
            )
        );
    }

}

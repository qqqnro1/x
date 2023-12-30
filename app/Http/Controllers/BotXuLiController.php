<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\Setting;
use App\Models\AccountMomo;
use App\Models\LichSuChoiMomo;
use App\Models\TaiXiu;
use App\Models\ChanLe;
use App\Models\ChanLe2;
use App\Models\Gap3;
use App\Models\Tong3So;
use App\Models\X1Phan3;
use App\Models\ConfigMessageMomo;
use App\Models\WEB2M;
use App\Models\Cache;
use App\Models\LichSuTraThuongTuan;
use App\Models\SettingPhanThuongTop;
use App\Models\NoHuu;
use App\Models\LichSuChoiNoHu;
use App\Models\LimitCron;

class BotXuLiController extends Controller
{
    public function __construct(){
        if(!isset($_GET['cron'])) {
            exit('Exit!');
        }
    }
    
    //Get giao dịch và lưu lại
    public function SaveGiaoDich(request $request){
        $type_cron = 'savegiaodich';

        $LimitCron = new LimitCron;
        $GetLimitCron = $LimitCron->where([
            'type' => $type_cron,
        ])->orderBy('id', 'desc')->limit(1);

        if ($GetLimitCron->count() > 0){
            $GetLimitCron = $GetLimitCron->first();

            $time = time();
            if ( ($time - $GetLimitCron->time) <= 10 ) {
                //return 'Cron không thể xử lý ngay lúc nầy';
            }
    
            $LimitCron->type = $type_cron;
            $LimitCron->time = time();
            $LimitCron->save();
        } else {
            $LimitCron->type = $type_cron;
            $LimitCron->time = time();
            $LimitCron->save();
        }

        //Setting
        $Setting = new Setting;
        $GetSetting = $Setting->first();

        //Check bảo trì
        if ($GetSetting->baotri == 1) {
            return;
        }

        //WEB2M
        $WEB2M = new WEB2M;

        // + Lưu lại lịch sử giao dịch
        $AccountMomo = new AccountMomo;
        $ListAccountMomo = $AccountMomo->where([
            'status' => 1,
        ])->get();

        foreach($ListAccountMomo as $row){

            //Lấy lịch sử giao dịch
            $ListGiaoDich = $WEB2M->GetGiaoDich($row->token);

            if (!isset($ListGiaoDich['status'])) {

                if ( isset($ListGiaoDich['momoMsg']['tranList']) ) {
                    $ListGD = $ListGiaoDich['momoMsg']['tranList'];
                    echo 'Lấy dữ liệu lịch sử => ' . $row->sdt;
                } else {
                    $ListGD = [];
                }
                
                //Lấy từng giao dịch
                foreach($ListGD as $res){

                    //Check giới hạn ngày
                    $AccountMomo = new AccountMomo;
                    $GetAccountMomo = $AccountMomo->where([
                        'sdt' => $row->sdt,
                    ])->first();

                    $gh1 = $GetAccountMomo->gioihan;

                    $LichSuChoiMomo = new LichSuChoiMomo;
                    $GetLichSuChoiMomo = $LichSuChoiMomo->whereDate('created_at', Carbon::today())->where('status', '!=', 5)->where([
                        'sdt_get' => $row->sdt,
                    ])->get();

                    $listLimit = 0;
                    foreach($GetLichSuChoiMomo as $crush){
                        $listLimit = $listLimit + $crush->tiennhan;
                    }

                    $listLimit = $listLimit + (int) $res['amount'];
                    $gh2 = $listLimit;

                    if ($gh2 < $gh1) {

                        if ( isset($res['comment']) ) {

                            //Đưa nội dung về chữ thường
                            $res['comment2'] = $res['comment'];
                            $res['comment'] = strtolower($res['comment']);
                            $res['comment'] = str_replace(' ', '', $res['comment']);

                            if ( $res['comment'] == 'h1' ) {
                        
                                //Setting
                                $Setting = new Setting;
                                $GetSetting = $Setting->first();
                        
                                //Check off game
                                if ($GetSetting->on_nohu == 1) {
                                    //Setting Nổ Hũ
                                    $NoHuu = new NoHuu;
                                    $Setting_NoHu = $NoHuu->first();

                                    //Kiểm tra giao dịch tồn tại chưa
                                    $LichSuChoiNoHu = new LichSuChoiNoHu;
                                    $Check = $LichSuChoiNoHu->where([
                                        'magiaodich' => (string) $res['tranId'],
                                    ])->count();

                                    if ($Check == 0) {
                                        if( (int) $res['amount'] == $Setting_NoHu->tiencuoc ){
                                            $nhanduoc = 0;
                                            $ketqua = 99;

                                            $socuoi = substr( (string) $res['tranId'], -4 );

                                            if ($socuoi[0] == $socuoi[1] && $socuoi[1] == $socuoi[2] && $socuoi[2] == $socuoi[3]) {
                                                $ketqua = 1;
                                                $LichSuChoiNoHu = new LichSuChoiNoHu;
                                                $GetLichSuChoiNoHu = $LichSuChoiNoHu->get();
                    
                                                foreach ($GetLichSuChoiNoHu as $sm) {
                                                    $nhanduoc = $nhanduoc + $sm->tiencuoc;
                                                    $nhanduoc = $nhanduoc - $sm->tiennhan;
                                                }
                    
                                                $nhanduoc = $nhanduoc + ($res['amount'] / 100) * $Setting_NoHu->ptvaohu;    
                                            }

                                            $LichSuChoiNoHu = new LichSuChoiNoHu;
                                            $LichSuChoiNoHu->sdt = $res['partnerId']; //SĐT người chơi
                                            $LichSuChoiNoHu->magiaodich = (string) $res['tranId']; //Mã giao dịch
                                            $LichSuChoiNoHu->tiencuoc = $res['amount']; //Tiền cược
                                            $LichSuChoiNoHu->tienvaohu = ($res['amount'] / 100) * $Setting_NoHu->ptvaohu;
                                            $LichSuChoiNoHu->tiennhan = $nhanduoc; //Nhận được
                                            $LichSuChoiNoHu->noidung = $res['comment2']; //Nội dung chuyển
                                            $LichSuChoiNoHu->ketqua = $ketqua;
                                            $LichSuChoiNoHu->status = 1; //Mặc định chờ xử lí
                                            $LichSuChoiNoHu->save();
                                        }
                                    }
                                }

                            } else {

                                //Kiểm tra giao dịch tồn tại chưa
                                $LichSuChoiMomo = new LichSuChoiMomo;
                                $Check = $LichSuChoiMomo->where([
                                    'magiaodich' => (string) $res['tranId'],
                                ])->count();

                                if ($Check == 0) {

                                    $NameGame = '';
                                    $tiennhan = 0;
                                    $ketqua = '';

                                    //Logic chẵn lẻ
                                    if ( $res['comment'] == 'c' || $res['comment'] == 'l' ) {
                                        if ($GetSetting->on_chanle == 1) {
                                            $ChanLe = new ChanLe;
                                            $Setting_ChanLe = $ChanLe->first();

                                            if ( (int) $res['amount'] >= $Setting_ChanLe->min && (int) $res['amount'] <= $Setting_ChanLe->max ) {
                                                $NameGame = 'Chẵn lẻ';

                                                //Logic
                                                $x = substr( (string) $res['tranId'] , -1);

                                                if ($x == 0 || $x == 9) {
                                                    $ra = 3;
                                                } else {
                                                    if ($x % 2 == 0) {
                                                        $ra = 1;
                                                    } else {
                                                        $ra = 2;
                                                    }
                                                }

                                                if ($res['comment'] == 'c') {
                                                    $dat = 1;
                                                } else {
                                                    $dat = 2;
                                                }

                                                if ($dat == $ra) {
                                                    $ketqua = 1;
                                                    $tiennhan = (int) $res['amount'] * $Setting_ChanLe->tile;
                                                } else {
                                                    $ketqua = 99;
                                                }
                                            }
                                        }
                                    }

                                    //Logic tài xỉu
                                    if ( $res['comment'] == 't' || $res['comment'] == 'x' ) {
                                        if ($GetSetting->on_taixiu == 1) {
                                            $TaiXiu = new TaiXiu;
                                            $Setting_TaiXiu = $TaiXiu->first();

                                            if ( (int) $res['amount'] >= $Setting_TaiXiu->min && (int) $res['amount'] <= $Setting_TaiXiu->max ) {
                                                $NameGame = 'Tài xỉu';

                                                //Logic
                                                $x = substr( (string) $res['tranId'] , -1);

                                                if ($x == 5 || $x == 6 || $x == 7 || $x == 8) {
                                                    $ra = 1;
                                                } else {

                                                    if ($x == 0 || $x == 9) {
                                                        $ra = 3;
                                                    } else {
                                                        $ra = 2;
                                                    }
                                                }

                                                if ($res['comment'] == 't') {
                                                    $dat = 1;
                                                } else {
                                                    $dat = 2;
                                                }

                                                if ($dat == $ra) {
                                                    $ketqua = 1;
                                                    $tiennhan = (int) $res['amount'] * $Setting_TaiXiu->tile;
                                                } else {
                                                    $ketqua = 99;
                                                }
                                            }
                                        }
                                    }

                                    //Logic chẵn lẻ 2
                                    if ( $res['comment'] == 'c2' || $res['comment'] == 'l2' ) {
                                        if ($GetSetting->on_chanle2 == 1) {
                                            $ChanLe2 = new ChanLe2;
                                            $Setting_ChanLe2 = $ChanLe2->first();

                                            if ( (int) $res['amount'] >= $Setting_ChanLe2->min && (int) $res['amount'] <= $Setting_ChanLe2->max ) {
                                                $NameGame = 'Chẵn lẻ 2';

                                                //Logic
                                                $x = substr( (string) $res['tranId'] , -1);

                                                if ($x % 2 == 0) {
                                                    $ra = 1;
                                                } else {
                                                    $ra = 2;
                                                }

                                                if ($res['comment'] == 'c2') {
                                                    $dat = 1;
                                                } else {
                                                    $dat = 2;
                                                }

                                                if ($dat == $ra) {
                                                    $ketqua = 1;
                                                    $tiennhan = (int) $res['amount'] * $Setting_ChanLe2->tile;
                                                } else {
                                                    $ketqua = 99;
                                                }
                                            }
                                        }
                                    }

                                    //Logic gấp 3
                                    if ( $res['comment'] == 'g3' ) {
                                        if ($GetSetting->on_gap3 == 1) {
                                            $Gap3 = new Gap3;
                                            $Setting_Gap3 = $Gap3->first();

                                            if ( (int) $res['amount'] >= $Setting_Gap3->min && (int) $res['amount'] <= $Setting_Gap3->max ) {
                                                $NameGame = 'Gấp 3';

                                                //Logic
                                                $x = substr( (string) $res['tranId'] , -2);
                                                $y = substr( (string) $res['tranId'] , -3);

                                                //Loại 1
                                                if ($x == '02' || $x == '13' || $x == '17' || $x == '19' || $x == '21' || $x == '29' || $x == '35' || $x == '37' || $x == '47' || $x == '49' || $x == '51' || $x == '54' || $x == '57' || $x == '63' || $x == '64' || $x == '74' || $x == '83' || $x == '91' || $x == '95' || $x == '96') {
                                                    $ketqua = 1;
                                                    $tiennhan = (int) $res['amount'] * $Setting_Gap3->tile1;
                                                } elseif ($x == '69' || $x == '96' || $x == '66' || $x == '99') {
                                                    $ketqua = 2;
                                                    $tiennhan = (int) $res['amount'] * $Setting_Gap3->tile2;
                                                } elseif ($y == '123' || $y == '234' || $y == '456' || $y == '678' || $y == '789') {
                                                    $ketqua = 3;
                                                    $tiennhan = (int) $res['amount'] * $Setting_Gap3->tile3;
                                                } else {
                                                    $ketqua = 99;
                                                }
                                            }
                                        }
                                    }

                                    //Logic tổng 3 số
                                    if ( $res['comment'] == 's' ) {
                                        if ($GetSetting->on_tong3so == 1) {
                                            $Tong3So = new Tong3So;
                                            $Setting_Tong3So = $Tong3So->first();

                                            if ( (int) $res['amount'] >= $Setting_Tong3So->min && (int) $res['amount'] <= $Setting_Tong3So->max ) {
                                                $NameGame = 'Tổng 3 số';
                                                $x = substr( (string) $res['tranId'] , -3);
                                                $y = str_split($x);

                                                $tong = 0 ;
                                                foreach($y as $ris){
                                                    $tong = $tong + (int) $ris;
                                                }

                                                if ($tong == '7' || $tong == '17' || $tong == '27') {
                                                    $ketqua = 1;
                                                    $tiennhan = (int) $res['amount'] * $Setting_Tong3So->tile1;
                                                } elseif ($tong == '8' || $tong == '18') {
                                                    $ketqua = 2;
                                                    $tiennhan = (int) $res['amount'] * $Setting_Tong3So->tile2;
                                                } elseif ($tong == '9' || $tong == '19'){
                                                    $ketqua = 3;
                                                    $tiennhan = (int) $res['amount'] * $Setting_Tong3So->tile3;
                                                } else {
                                                    $ketqua = 99;
                                                }
                                            }
                                        }
                                    }

                                    //Logic 1 phần 3
                                    if ( $res['comment'] == 'n1' || $res['comment'] == 'n2' || $res['comment'] == 'n3' ) {
                                        if ($GetSetting->on_1phan3 == 1) {
                                            $X1Phan3 = new X1Phan3;
                                            $Setting_1Phan3 = $X1Phan3->first();

                                            if ( (int) $res['amount'] >= $Setting_1Phan3->min && (int) $res['amount'] <= $Setting_1Phan3->max ) {
                                                $NameGame = '1 phần 3';

                                                $x = substr( (string) $res['tranId'] , -1);
                                                if ($res['comment'] == 'n1') {
                                                    if ($x == '1' || $x == '2' || $x == '3') {
                                                        $ketqua = 1;
                                                        $tiennhan = (int) $res['amount'] * $Setting_1Phan3->tile;
                                                    } else {
                                                        $ketqua = 99;
                                                    }
                                                } elseif ($res['comment'] == 'n2') {
                                                    if ($x == '4' || $x == '5' || $x == '6') {
                                                        $ketqua = 1;
                                                        $tiennhan = (int) $res['amount'] * $Setting_1Phan3->tile;
                                                    } else {
                                                        $ketqua = 99;
                                                    }
                                                } elseif ($res['comment'] == 'n3') {
                                                    if ($x == '7' || $x == '8' || $x == '9') {
                                                        $ketqua = 1;
                                                        $tiennhan = (int) $res['amount'] * $Setting_1Phan3->tile;
                                                    } else {
                                                        $ketqua = 99;
                                                    }
                                                }
                                            }
                                        }
                                    }

                                    if ($NameGame != '') {
                                        $LichSuChoiMomo = new LichSuChoiMomo;
                                        $LichSuChoiMomo->sdt = $res['partnerId']; //SĐT người chơi
                                        $LichSuChoiMomo->sdt_get = $row->sdt; //SĐT admin
                                        $LichSuChoiMomo->magiaodich = (string) $res['tranId']; //Mã giao dịch
                                        $LichSuChoiMomo->tiencuoc = $res['amount']; //Tiền cược
                                        $LichSuChoiMomo->tiennhan = $tiennhan; //Nhận được
                                        $LichSuChoiMomo->trochoi = $NameGame; //Tên trò chơi
                                        $LichSuChoiMomo->noidung = $res['comment2']; //Nội dung chuyển
                                        $LichSuChoiMomo->ketqua = $ketqua; //Kết quả Thắng hay Thua
                                        $LichSuChoiMomo->status = 1; //Mặc định chờ xử lí
                                        $LichSuChoiMomo->save();
                                    }

                                }
                            }

                        }
                    }
                }

            }
        }

        echo "<br />".'+ Lấy giao dịch thành công';        
    }

    //Xử lý giao dịch
    public function TraThuongGiaoDich(request $request){
        $type_cron = 'trathuonggiaodich';

        $LimitCron = new LimitCron;
        $GetLimitCron = $LimitCron->where([
            'type' => $type_cron,
        ])->orderBy('id', 'desc')->limit(1);

        if ($GetLimitCron->count() > 0){
            $GetLimitCron = $GetLimitCron->first();

            $time = time();
            if ( ($time - $GetLimitCron->time) <= 10 ) {
                //return 'Cron không thể xử lý ngay lúc nầy';
            }
    
            $LimitCron->type = $type_cron;
            $LimitCron->time = time();
            $LimitCron->save();
        } else {
            $LimitCron->type = $type_cron;
            $LimitCron->time = time();
            $LimitCron->save();
        }


        $WEB2M = new WEB2M;
        $ConfigMessageMomo = new ConfigMessageMomo;

        //Bảo trì
        $Setting = new Setting;
        $GetSetting = $Setting->first();

        $GetSetting->baotri;

        //Check bảo trì
        if ($GetSetting->baotri == 1) {
            echo 'Máy chủ bảo trì!';
            return;
        }

        $LichSuChoiMomo = new LichSuChoiMomo;
        $ListLichSuChoiMomo = $LichSuChoiMomo->where([
            'status' => 1,
        ])->limit(15)->get();

        foreach($ListLichSuChoiMomo as $row){
            //Kiểm tra lại
            $Check = $LichSuChoiMomo->where([
                'id' => $row->id,
            ])->first();
            
            //Nếu vẫn đang ở trạng thái chờ
            if ($Check->status == 1) {

                //Chuyển thành trạng thái đang xử lí
                $Check->status = 2;
                $Check->save();

                $AccountMomo = new AccountMomo;
                $Account = $AccountMomo->where([
                    'sdt' => $Check->sdt_get,
                ])->first();

                $GetMessageTraThuong = $ConfigMessageMomo->where([
                    'type' => 'tra-thuong',
                ])->first();

                if ($Check->tiennhan > 0) {
                    
                        $res = $WEB2M->Bank(
                            $Account->token,
                            $Check->sdt,
                            $Account->password,
                            $Check->tiennhan,
                            $GetMessageTraThuong->message.' '.$Check->magiaodich
                        );
    
                        
                        if ( isset($res['status']) && $res['status'] == 200) {
                            $Check->status = 3;
                            $Check->save();
                        } else {
                            $Check->status = 4;
                            $Check->save();
                        }
                        
                        var_dump($res);
                        
                        sleep(3);
                    
                    
                } else {
                    $Check->status = 3;
                    $Check->save();
                }
            }

        }

        echo "<br />".'+ Xử lí giao dịch hoàn tất';
    }

    //Xử lí lại giao dịch lỗi
    public function TraThuongGiaoDichError(request $request){
        $type_cron = 'trathuonggiaodicherror';

        $LimitCron = new LimitCron;
        $GetLimitCron = $LimitCron->where([
            'type' => $type_cron,
        ])->orderBy('id', 'desc')->limit(1);

        if ($GetLimitCron->count() > 0){
            $GetLimitCron = $GetLimitCron->first();

            $time = time();
            if ( ($time - $GetLimitCron->time) <= 10 ) {
                //return 'Cron không thể xử lý ngay lúc nầy';
            }
    
            $LimitCron->type = $type_cron;
            $LimitCron->time = time();
            $LimitCron->save();
        } else {
            $LimitCron->type = $type_cron;
            $LimitCron->time = time();
            $LimitCron->save();
        }

        $WEB2M = new WEB2M;
        $ConfigMessageMomo = new ConfigMessageMomo;

        //Bảo trì
        $Setting = new Setting;
        $GetSetting = $Setting->first();

        $GetSetting->baotri;

        //Check bảo trì
        if ($GetSetting->baotri == 1) {
            echo 'Máy chủ bảo trì!';
            return;
        }

        $LichSuChoiMomo = new LichSuChoiMomo;
        $ListLichSuChoiMomo = $LichSuChoiMomo->where([
            'status' => 4,
        ])->limit(15)->get();

        foreach($ListLichSuChoiMomo as $row){
            //Kiểm tra lại
            $Check = $LichSuChoiMomo->where([
                'id' => $row->id,
            ])->first();
            
            //Nếu vẫn đang ở trạng thái chờ
            if ($Check->status == 4) {

                //Chuyển thành trạng thái đang xử lí
                $Check->status = 2;
                $Check->save();

                $AccountMomo = new AccountMomo;
                $Account = $AccountMomo->where([
                    'sdt' => $Check->sdt_get,
                ])->first();

                $GetMessageTraThuong = $ConfigMessageMomo->where([
                    'type' => 'tra-thuong',
                ])->first();

                if ($Check->tiennhan > 0) {

                        $res = $WEB2M->Bank(
                            $Account->token,
                            $Check->sdt,
                            $Account->password,
                            $Check->tiennhan,
                            $GetMessageTraThuong->message.' '.$Check->magiaodich
                        );
    
                        
                        if ( isset($res['status']) && $res['status'] == 200) {
                            $Check->status = 3;
                            $Check->save();
                        } else {
                            $Check->status = 4;
                            $Check->save();
                        }
                        
                        var_dump($res);
                        
                        sleep(3);
                    
                    
                } else {
                    $Check->status = 3;
                    $Check->save();
                }
            }

        }

        echo "<br />".'+ Xử lí giao dịch hoàn tất';
    }

    //Trả thường top tuần
    public function GetTraThuongTuan(request $request){
        $type_cron = 'gettrathuongtuan';

        $LimitCron = new LimitCron;
        $GetLimitCron = $LimitCron->where([
            'type' => $type_cron,
        ])->orderBy('id', 'desc')->limit(1);

        if ($GetLimitCron->count() > 0){
            $GetLimitCron = $GetLimitCron->first();

            $time = time();
            if ( ($time - $GetLimitCron->time) <= 10 ) {
                //return 'Cron không thể xử lý ngay lúc nầy';
            }
    
            $LimitCron->type = $type_cron;
            $LimitCron->time = time();
            $LimitCron->save();
        } else {
            $LimitCron->type = $type_cron;
            $LimitCron->time = time();
            $LimitCron->save();
        }

        //Setting
        $Setting = new Setting;
        $GetSetting = $Setting->first();

        //Check bảo trì
        if ($GetSetting->baotri == 1) {
            return;
        }

        //Check game có bật không
        if ($GetSetting->on_trathuongtuan != 1) {
            return;
        }

        $SettingPhanThuongTop = new SettingPhanThuongTop;
        $LichSuChoiMomo = new LichSuChoiMomo;
        $Cache = new Cache;
        $GetCache = $Cache->first();

        //
        $TimeUpdate = $GetCache->time_bank_top_tuan;
        $TimeNow = date("d/m/Y");

        //Cập nhật khi bị rổng
        if ($GetCache->time_bank_top_tuan == '') {
            $GetCache->time_bank_top_tuan = date("d/m/Y");
            $GetCache->save();
        }

        $weekday = date("l");
        $weekday = strtolower($weekday);
        
        if ($weekday == 'monday') {
            if ($TimeNow != $TimeUpdate) {

                //Cập nhật lại thời gian update
                $GetCache->time_bank_top_tuan = date("d/m/Y");
                $GetCache->save();

                //Thuật toán tìm TOP tuần
                $TopTuan = [];
                $dem = 0;

                $ListSDT = [];
                $st = 0;

                $now = Carbon::now();
                $weekStartDate = $now->startOfWeek()->format('Y-m-d H:i');
                $weekEndDate = $now->endOfWeek()->format('Y-m-d H:i');
                $date = \Carbon\Carbon::today()->subDays(7);

                $ListLichSuChoiMomo = $LichSuChoiMomo->where('created_at','>=',$date)->get();

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
                    ])->where('created_at','>=',$date)->get();

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


                $dem = 1;
                foreach ($UserTopTuan as $row) {
                    $SettingPhanThuongTop = new SettingPhanThuongTop;
                    $GetSettingPhanThuongTop = $SettingPhanThuongTop->where([
                        'top' => $dem,
                    ])->first();

                    $LichSuTraThuongTuan = new LichSuTraThuongTuan;
                    $LichSuTraThuongTuan->sdt = $row['sdt'];
                    $LichSuTraThuongTuan->sotien = $GetSettingPhanThuongTop->phanthuong;
                    $LichSuTraThuongTuan->status = 1;
                    $LichSuTraThuongTuan->save();    
                    
                    $dem ++;
                }

            }
        }

        echo "<br />".'+ Lưu dữ liệu trả thưởng hoàn tất';
    }

    public function XuLiTraThuongTuan(request $request){
        $type_cron = 'xulitrathuongtuan';

        $LimitCron = new LimitCron;
        $GetLimitCron = $LimitCron->where([
            'type' => $type_cron,
        ])->orderBy('id', 'desc')->limit(1);

        if ($GetLimitCron->count() > 0){
            $GetLimitCron = $GetLimitCron->first();

            $time = time();
            if ( ($time - $GetLimitCron->time) <= 10 ) {
                //return 'Cron không thể xử lý ngay lúc nầy';
            }
    
            $LimitCron->type = $type_cron;
            $LimitCron->time = time();
            $LimitCron->save();
        } else {
            $LimitCron->type = $type_cron;
            $LimitCron->time = time();
            $LimitCron->save();
        }

        $LichSuTraThuongTuan = new LichSuTraThuongTuan;
        $GetLichSuTraThuongTuan = $LichSuTraThuongTuan->where([
            'status' => 1,
        ])->limit(15)->get();

        $WEB2M = new WEB2M;
        foreach ($GetLichSuTraThuongTuan as $row) {
            $Check = $LichSuTraThuongTuan->where([
                'id' => $row->id,
            ])->first();

            if ($Check->status == 1) {

                //Đang xử lý
                $Check->status = 2;
                $Check->save();

                //Lấy tài khoản Momo
                $AccountMomo = new AccountMomo;
                $Account = $AccountMomo->where([
                    'status' => 1
                ])->first();

                //Lấy message
                $ConfigMessageMomo = new ConfigMessageMomo;
                $GetMessageTraThuong = $ConfigMessageMomo->where([
                    'type' => 'thuong-top-tuan',
                ])->first();

                        $res = $WEB2M->Bank(
                            $Account->token,
                            $Check->sdt,
                            $Account->password,
                            $Check->sotien,
                            $GetMessageTraThuong->message.' '.$Check->magiaodich
                        );
    
                        
                        if ( isset($res['status']) && $res['status'] == 200) {
                            $Check->status = 3;
                            $Check->save();
                        } else {
                            $Check->status = 4;
                            $Check->save();
                        }
                        
                        var_dump($res);
                        
                        sleep(3);                

            }
        }
        echo 'Xử lí giao dịch hoàn tất';
    }

    public function XuLiTraThuongTuanLoi(request $request){
        $type_cron = 'xulitrathuongtuanloi';

        $LimitCron = new LimitCron;
        $GetLimitCron = $LimitCron->where([
            'type' => $type_cron,
        ])->orderBy('id', 'desc')->limit(1);

        if ($GetLimitCron->count() > 0){
            $GetLimitCron = $GetLimitCron->first();

            $time = time();
            if ( ($time - $GetLimitCron->time) <= 10 ) {
                //return 'Cron không thể xử lý ngay lúc nầy';
            }
    
            $LimitCron->type = $type_cron;
            $LimitCron->time = time();
            $LimitCron->save();
        } else {
            $LimitCron->type = $type_cron;
            $LimitCron->time = time();
            $LimitCron->save();
        }

        $LichSuTraThuongTuan = new LichSuTraThuongTuan;
        $GetLichSuTraThuongTuan = $LichSuTraThuongTuan->where([
            'status' => 4,
        ])->limit(15)->get();

        $WEB2M = new WEB2M;
        foreach ($GetLichSuTraThuongTuan as $row) {
            $Check = $LichSuTraThuongTuan->where([
                'id' => $row->id,
            ])->first();

            if ($Check->status == 4) {

                //Đang xử lý
                $Check->status = 2;
                $Check->save();

                //Lấy tài khoản Momo
                $AccountMomo = new AccountMomo;
                $Account = $AccountMomo->where([
                    'status' => 1
                ])->first();

                //Lấy message
                $ConfigMessageMomo = new ConfigMessageMomo;
                $GetMessageTraThuong = $ConfigMessageMomo->where([
                    'type' => 'thuong-top-tuan',
                ])->first();
                        $res = $WEB2M->Bank(
                            $Account->token,
                            $Check->sdt,
                            $Account->password,
                            $Check->sotien,
                            $GetMessageTraThuong->message.' '.$Check->magiaodich
                        );
    
                        
                        if ( isset($res['status']) && $res['status'] == 200) {
                            $Check->status = 3;
                            $Check->save();
                        } else {
                            $Check->status = 4;
                            $Check->save();
                        }
                        
                        var_dump($res);
                        
                        sleep(3);
                
            }
        }
        echo "<br />".'+ Xử lí giao dịch hoàn tất';
    }

    public function XuLiNoHuu(request $request){
        $type_cron = 'xulinohu';

        $LimitCron = new LimitCron;
        $GetLimitCron = $LimitCron->where([
            'type' => $type_cron,
        ])->orderBy('id', 'desc')->limit(1);

        if ($GetLimitCron->count() > 0){
            $GetLimitCron = $GetLimitCron->first();

            $time = time();
            if ( ($time - $GetLimitCron->time) <= 10 ) {
                //return 'Cron không thể xử lý ngay lúc nầy';
            }
    
            $LimitCron->type = $type_cron;
            $LimitCron->time = time();
            $LimitCron->save();
        } else {
            $LimitCron->type = $type_cron;
            $LimitCron->time = time();
            $LimitCron->save();
        }

        //Setting nổ hũ
        $NoHuu = new NoHuu;
        $Setting_NoHu = $NoHuu->first();

        $LichSuChoiNoHu = new LichSuChoiNoHu;
        $GetLichSuChoiNoHu = $LichSuChoiNoHu->where([
            'status' => 1,
        ])->limit(15)->get();

        $WEB2M = new WEB2M;
        foreach ($GetLichSuChoiNoHu as $row) {
            $Check = $LichSuChoiNoHu->where([
                'id' => $row->id,
            ])->first();

            if ($Check->status == 1) {

                //Đang xử lý
                $Check->status = 2;
                $Check->save();

                //Lấy tài khoản Momo
                $AccountMomo = new AccountMomo;
                $Account = $AccountMomo->where([
                    'status' => 1
                ])->first();

                //Lấy message
                $ConfigMessageMomo = new ConfigMessageMomo;
                $GetConfigMessageMomo = $ConfigMessageMomo->where([
                    'type' => 'no-huu',
                ])->first();

                if ($Check->ketqua == 1){

                        $res = $WEB2M->Bank(
                            $Account->token,
                            $Check->sdt,
                            $Account->password,
                            $Check->tiennhan,
                            $GetMessageTraThuong->message.' '.$Check->magiaodich
                        );
    
                        
                        if ( isset($res['status']) && $res['status'] == 200) {
                            $Check->status = 3;
                            $Check->save();
                        } else {
                            $Check->status = 4;
                            $Check->save();
                        }
                        
                        var_dump($res);
                        
                        sleep(3);
                
                    
                } else {
                    $res->status = 3;
                    $res->save();
                }              
            }
        }
        echo "<br />".'+ Xử lí giao dịch hoàn tất';
    }

    public function XuLiNoHuuLoi(request $request){
        $type_cron = 'xulinohuloi';

        $LimitCron = new LimitCron;
        $GetLimitCron = $LimitCron->where([
            'type' => $type_cron,
        ])->orderBy('id', 'desc')->limit(1);

        if ($GetLimitCron->count() > 0){
            $GetLimitCron = $GetLimitCron->first();

            $time = time();
            if ( ($time - $GetLimitCron->time) <= 10 ) {
                //return 'Cron không thể xử lý ngay lúc nầy';
            }
    
            $LimitCron->type = $type_cron;
            $LimitCron->time = time();
            $LimitCron->save();
        } else {
            $LimitCron->type = $type_cron;
            $LimitCron->time = time();
            $LimitCron->save();
        }

        //Setting nổ hũ
        $NoHuu = new NoHuu;
        $Setting_NoHu = $NoHuu->first();

        $LichSuChoiNoHu = new LichSuChoiNoHu;
        $GetLichSuChoiNoHu = $LichSuChoiNoHu->where([
            'status' => 4,
        ])->limit(15)->get();

        $WEB2M = new WEB2M;
        foreach ($GetLichSuChoiNoHu as $row) {
            $Check = $LichSuChoiNoHu->where([
                'id' => $row->id,
            ])->first();

            if ($Check->status == 4) {
                
                //Đang xử lý
                $Check->status = 2;
                $Check->save();

                //Lấy tài khoản Momo
                $AccountMomo = new AccountMomo;
                $Account = $AccountMomo->where([
                    'status' => 1
                ])->first();

                //Lấy message
                $ConfigMessageMomo = new ConfigMessageMomo;
                $GetConfigMessageMomo = $ConfigMessageMomo->where([
                    'type' => 'no-huu',
                ])->first();

                if ($Check->ketqua == 1){

                        $res = $WEB2M->Bank(
                            $Account->token,
                            $Check->sdt,
                            $Account->password,
                            $Check->tiennhan,
                            $GetMessageTraThuong->message.' '.$Check->magiaodich
                        );
    
                        
                        if ( isset($res['status']) && $res['status'] == 200) {
                            $Check->status = 3;
                            $Check->save();
                        } else {
                            $Check->status = 4;
                            $Check->save();
                        }
                        
                        var_dump($res);
                        
                        sleep(3);
                
                } else {
                    $Check->status = 3;
                    $Check->save();
                }              
            }
        }
        echo "<br />".'+ Xử lí giao dịch hoàn tất';
    }

}
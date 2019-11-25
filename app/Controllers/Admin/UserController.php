<?php

namespace App\Controllers\Admin;

use App\Models\User;
use App\Models\Code;
use App\Models\Ip;
use App\Models\RadiusBan;
use App\Models\Relay;
use App\Controllers\AdminController;
use App\Services\Config;
use App\Services\Auth;
use App\Utils;
use App\Utils\Hash;
use App\Utils\Radius;
use App\Utils\QQWry;
use App\Utils\Tools;
use App\Models\Payback;  // 原来是缺少这个 song 

class UserController extends AdminController
{
    public function index($request, $response, $args)
    {
        $table_config['total_column'] = array("op" => "操作", "id" => "ID", "user_name" => "用户名",
                            "remark" => "备注", "email" => "邮箱", "money" => "金钱",
                            "im_type" => "联络方式类型", "im_value" => "联络方式详情",
                            "node_group" => "群组", "expire_in" => "账户过期时间",
                            "class" => "等级", "class_expire" => "等级过期时间",
                            "passwd" => "连接密码","port" => "连接端口", "method" => "加密方式",
                            "protocol" => "连接协议", "obfs" => "连接混淆方式",
                            "online_ip_count" => "在线IP数", "last_ss_time" => "上次使用时间",
                            "used_traffic" => "已用流量/GB", "enable_traffic" => "总流量/GB",
                            "last_checkin_time" => "上次签到时间", "today_traffic" => "今日流量/MB",
                            "enable" => "是否启用", "reg_date" => "注册时间",
                            "reg_ip" => "注册IP", "auto_reset_day" => "自动重置流量日",
                            "auto_reset_bandwidth" => "自动重置流量/GB", "ref_by" => "邀请人ID", "ref_by_user_name" => "邀请人用户名",
							"top_up" => "累计充值");
        $table_config['default_show_column'] = array("op", "id", "user_name", "remark", "email");
        $table_config['ajax_url'] = 'user/ajax';
        return $this->view()->assign('table_config', $table_config)->display('admin/user/index.tpl');
    }

    public function search($request, $response, $args)
    {
        $pageNum = 1;
        $text=$args["text"];
        if (isset($request->getQueryParams()["page"])) {
            $pageNum = $request->getQueryParams()["page"];
        }

        $users = User::where("email", "LIKE", "%".$text."%")->orWhere("user_name", "LIKE", "%".$text."%")->orWhere("im_value", "LIKE", "%".$text."%")->orWhere("port", "LIKE", "%".$text."%")->orWhere("remark", "LIKE", "%".$text."%")->paginate(20, ['*'], 'page', $pageNum);
        $users->setPath('/admin/user/search/'.$text);



        //Ip::where("datetime","<",time()-90)->get()->delete();
        $total = Ip::where("datetime", ">=", time()-90)->orderBy('userid', 'desc')->get();


        $userip=array();
        $useripcount=array();
        $regloc=array();

        $iplocation = new QQWry();
        foreach ($users as $user) {
            $useripcount[$user->id]=0;
            $userip[$user->id]=array();

            $location=$iplocation->getlocation($user->reg_ip);
            $regloc[$user->id]=iconv('gbk', 'utf-8//IGNORE', $location['country'].$location['area']);
        }



        foreach ($total as $single) {
            if (isset($useripcount[$single->userid])) {
                if (!isset($userip[$single->userid][$single->ip])) {
                    $useripcount[$single->userid]=$useripcount[$single->userid]+1;
                    $location=$iplocation->getlocation($single->ip);
                    $userip[$single->userid][$single->ip]=iconv('gbk', 'utf-8//IGNORE', $location['country'].$location['area']);
                }
            }
        }


        return $this->view()->assign('users', $users)->assign("regloc", $regloc)->assign("useripcount", $useripcount)->assign("userip", $userip)->display('admin/user/index.tpl');
    }

    public function sort($request, $response, $args)
    {
        $pageNum = 1;
        $text=$args["text"];
        $asc=$args["asc"];
        if (isset($request->getQueryParams()["page"])) {
            $pageNum = $request->getQueryParams()["page"];
        }


        $users->setPath('/admin/user/sort/'.$text."/".$asc);



        //Ip::where("datetime","<",time()-90)->get()->delete();
        $total = Ip::where("datetime", ">=", time()-90)->orderBy('userid', 'desc')->get();


        $userip=array();
        $useripcount=array();
        $regloc=array();

        $iplocation = new QQWry();
        foreach ($users as $user) {
            $useripcount[$user->id]=0;
            $userip[$user->id]=array();

            $location=$iplocation->getlocation($user->reg_ip);
            $regloc[$user->id]=iconv('gbk', 'utf-8//IGNORE', $location['country'].$location['area']);
        }



        foreach ($total as $single) {
            if (isset($useripcount[$single->userid])) {
                if (!isset($userip[$single->userid][$single->ip])) {
                    $useripcount[$single->userid]=$useripcount[$single->userid]+1;
                    $location=$iplocation->getlocation($single->ip);
                    $userip[$single->userid][$single->ip]=iconv('gbk', 'utf-8//IGNORE', $location['country'].$location['area']);
                }
            }
        }


        return $this->view()->assign('users', $users)->assign("regloc", $regloc)->assign("useripcount", $useripcount)->assign("userip", $userip)->display('admin/user/index.tpl');
    }


    public function edit($request, $response, $args)
    {
        $id = $args['id'];
        $user = User::find($id);
        if ($user == null) {
        }
        return $this->view()->assign('edit_user', $user)->display('admin/user/edit.tpl');
    }

    public function update($request, $response, $args)
    {
        $id = $args['id'];
        $user = User::find($id);

        $email1=$user->email;

        $user->email =  $request->getParam('email');

        $email2=$request->getParam('email');

        $passwd=$request->getParam('passwd');

        Radius::ChangeUserName($email1, $email2, $passwd);


        if ($request->getParam('pass') != '') {
            $user->pass = Hash::passwordHash($request->getParam('pass'));
            $user->clean_link();
        }

        $user->auto_reset_day =  $request->getParam('auto_reset_day');
        $user->auto_reset_bandwidth = $request->getParam('auto_reset_bandwidth');
        $origin_port = $user->port;
        $user->port =  $request->getParam('port');

        $relay_rules = Relay::where('user_id', $user->id)->where('port', $origin_port)->get();
        foreach ($relay_rules as $rule) {
            $rule->port = $user->port;
            $rule->save();
        }

        $user->passwd = $request->getParam('passwd');
        $user->protocol = $request->getParam('protocol');
        $user->protocol_param = $request->getParam('protocol_param');
        $user->obfs = $request->getParam('obfs');
        $user->obfs_param = $request->getParam('obfs_param');
        $user->is_multi_user = $request->getParam('is_multi_user');
        $user->transfer_enable = Tools::toGB($request->getParam('transfer_enable'));
        $user->invite_num = $request->getParam('invite_num');
        $user->method = $request->getParam('method');
        $user->node_speedlimit = $request->getParam('node_speedlimit');
        $user->node_connector = $request->getParam('node_connector');
        $user->enable = $request->getParam('enable');
        $user->is_admin = $request->getParam('is_admin');
        $user->ga_enable = $request->getParam('ga_enable');
        $user->node_group = $request->getParam('group');
        $user->ref_by = $request->getParam('ref_by');
        $user->remark = $request->getParam('remark');
        $user->money = $request->getParam('money');
        $user->class = $request->getParam('class');
        $user->class_expire = $request->getParam('class_expire');
        $user->expire_in = $request->getParam('expire_in');

        $user->forbidden_ip = str_replace(PHP_EOL, ",", $request->getParam('forbidden_ip'));
        $user->forbidden_port = str_replace(PHP_EOL, ",", $request->getParam('forbidden_port'));

        if (!$user->save()) {
            $rs['ret'] = 0;
            $rs['msg'] = "修改失败";
            return $response->getBody()->write(json_encode($rs));
        }
        $rs['ret'] = 1;
        $rs['msg'] = "修改成功";
        return $response->getBody()->write(json_encode($rs));
    }

    public function delete($request, $response, $args)
    {
        $id = $request->getParam('id');
        $user = User::find($id);

        $email1=$user->email;

                # code...
        //如果存在邀请，并且用户的使用流量 和 使用天数 合起来小于 128G就删除用户 
        $used_time = floor( ( time() - strtotime($user->reg_date) ) / 86400 );
        $used_data = floor( ($user->u + $user->d) / 1073741824 );

        if ($user->ref_by != 0 && ( ($used_time + $used_data) < 128 )) {
            # code...
            $ref_user = User::find($user->ref_by);
            //这里 -1 代表是注册返利  -2 代表是 删除账号 取消返利
            $ref_payback = Payback::where('total','=',-1)->where('userid','=',$user->id)->where('ref_by','=',$user->ref_by)->first();
            //这里 查询一下是否已经存在 扣除余额的情况，统计一下 -2 情况的数量 
            $pays = Payback::where('total','=',-2)->where('userid','=',$user->id)->where('ref_by','=', $user->ref_by)->count();
            //先判断一下这个邀请人是否还存在   判断是否存在已扣除的情况
            if ($ref_user->id != null  && $ref_payback->ref_get != null && $pays < 1) {    //如果存在
                $ref_user->money -= $ref_payback->ref_get;     //这里用当前余额，减去当初返利的余额。
                //扣除邀请的流量！
                $ref_user->transfer_enable -= Config::get('invite_gift') * 1024 * 1024 * 1024;
                $ref_user->save();
                //写入返利日志
                $Payback = new Payback();
                #echo $user->id;
                #echo ' ';
                $Payback->total = -2;
                $Payback->userid = $user->id;  //用户注册的ID 
                $Payback->ref_by = $user->ref_by;  //邀请人ID
                $Payback->ref_get = - $ref_payback->ref_get;
                $Payback->datetime = time();
                $Payback->save();
            }
        }


        if (!$user->kill_user()) {
            $rs['ret'] = 0;
            $rs['msg'] = "删除失败";
            return $response->getBody()->write(json_encode($rs));
        }
        $rs['ret'] = 1;
        $rs['msg'] = "删除成功";
        return $response->getBody()->write(json_encode($rs));
    }
    
    public function changetouser($request, $response, $args)
    {
        $userid = $request->getParam('userid');
        $adminid = $request->getParam('adminid');
        $user = User::find($userid);
        $admin = User::find($adminid);
        $expire_in = time()+60*60;
      
        if (!$admin->is_admin || !$user || !Auth::getUser()->isLogin) {
            $rs['ret'] = 0;
            $rs['msg'] = "非法请求";
            return $response->getBody()->write(json_encode($rs));
        }
        
        Utils\Cookie::set([
            "uid" => $user->id,
            "email" => $user->email,
            "key" => Hash::cookieHash($user->pass),
            "ip" => md5($_SERVER["REMOTE_ADDR"].Config::get('key').$user.$expire_in),
            "expire_in" =>  $expire_in,
            "old_uid" => Utils\Cookie::get('uid'),
            "old_email" => Utils\Cookie::get('email'),
            "old_key" => Utils\Cookie::get('key'),
            "old_ip" => Utils\Cookie::get('ip'),
            "old_expire_in" => Utils\Cookie::get('expire_in'),
            "old_local" =>  $request->getParam('local')
        ],  $expire_in);
        $rs['ret'] = 1;
        $rs['msg'] = "切换成功";
        return $response->getBody()->write(json_encode($rs));
    }

	public function ajax($request, $response, $args)
	{		
        //得到排序的方式
        $order = $request->getParam('order')[0]['dir'];
        //得到排序字段的下标
        $order_column = $request->getParam('order')[0]['column'];
        //根据排序字段的下标得到排序字段
        $order_field = $request->getParam('columns')[$order_column]['data'];
        $limit_start = $request->getParam('start');
        $limit_length = $request->getParam('length');
        $search = $request->getParam('search')['value'];
        
		$users=array();
		$count_filtered=0;

        if ($search) {
            $users = User::orderBy($order_field,$order)
                    ->skip($limit_start)->limit($limit_length)
                    ->where(
                        function ($query) use ($search) {
                            $query->where('id','LIKE',"%$search%")
								->orwhere('user_name','LIKE',"%$search%")
								->orwhere('email','LIKE',"%$search%")
								->orwhere('im_value','LIKE',"%$search%")
								->orwhere('port','LIKE',"%$search%");
							}
						)
                    ->get();
            $count_filtered = User::where(
                        function ($query)use($search) {
                            $query->where('id','LIKE',"%$search%")
								->orwhere('user_name','LIKE',"%$search%")
								->orwhere('email','LIKE',"%$search%")
								->orwhere('im_value','LIKE',"%$search%")
								->orwhere('port','LIKE',"%$search%");
							}
						)->count();
		}
		else{
            $users = User::orderBy($order_field,$order)
                ->skip($limit_start)->limit($limit_length)
                ->get();
            $count_filtered = User::count();
        }
		        
		$data=array();
		foreach ($users as $user) {
			$tempdata=array();
			//model里是casts所以没法直接 $tempdata=(array)$user
			$tempdata['op']='<a class="btn btn-brand" href="/admin/user/'.$user->id.'/edit">编辑</a>
                    <a class="btn btn-brand-accent" id="delete" href="javascript:void(0);" onClick="delete_modal_show(\''.$user->id.'\')">删除</a>
                    <a class="btn btn-brand" id="changetouser" href="javascript:void(0);" onClick="changetouser_modal_show(\''.$user->id.'\')">切换为该用户</a>';;
			$tempdata['id']=$user->id;
			$tempdata['user_name']=$user->user_name;
			$tempdata['remark']=$user->remark;
			$tempdata['email']=$user->email;
			$tempdata['money']=$user->money;
			$tempdata['im_value']=$user->im_value;			
			switch($user->im_type) {
				case 1:
				$tempdata['im_type'] = '微信';
				break;
            case 2:
				$tempdata['im_type'] = 'QQ';
				break;
            case 3:
				$tempdata['im_type'] = 'Google+';
				break;
            default:
				$tempdata['im_type'] = 'Telegram';
				$tempdata['im_value'] = '<a href="https://telegram.me/'.$user->im_value.'">'.$user->im_value.'</a>';
			}
			$tempdata['node_group']=$user->node_group;
			$tempdata['expire_in']=$user->expire_in;
			$tempdata['class']=$user->class;
			$tempdata['class_expire']=$user->class_expire;
			$tempdata['passwd']=$user->passwd;
			$tempdata['port']=$user->port;
			$tempdata['method']=$user->method;
			$tempdata['protocol']=$user->protocol;
			$tempdata['obfs']=$user->obfs;
			$tempdata['online_ip_count']=$user->online_ip_count();
			$tempdata['last_ss_time']=$user->lastSsTime();
			$tempdata['used_traffic']=Tools::flowToGB($user->u + $user->d);
			$tempdata['enable_traffic']=Tools::flowToGB($user->transfer_enable);
			$tempdata['last_checkin_time']=$user->lastCheckInTime();
			$tempdata['today_traffic']=Tools::flowToMB($user->u + $user->d-$user->last_day_t);
			$tempdata['enable']=$user->enable == 1 ? "可用" : "禁用";
			$tempdata['reg_date']=$user->reg_date;
			$tempdata['reg_ip']=$user->reg_ip;
			$tempdata['auto_reset_day']=$user->auto_reset_day;
			$tempdata['auto_reset_bandwidth']=$user->auto_reset_bandwidth;			
            $tempdata['ref_by']= $user->ref_by;
			if ($user->ref_by == 0) {
				$tempdata['ref_by_user_name'] = "系统邀请";
			}
			else {
				$ref_user = User::find($user->ref_by);
				if ($ref_user == null) {
					$tempdata['ref_by_user_name'] = "邀请人已经被删除";
				}
				else {
					$tempdata['ref_by_user_name'] = $ref_user->user_name;
				}
			}
			$codes=Code::where('userid',$user->id)->get();
            $tempdata['top_up']=0;
            foreach($codes as $code){
				$tempdata['top_up']+=$code->number;
            }
            $tempdata['top_up']=round($tempdata['top_up'],2);

			array_push($data,$tempdata);
		}         
        $info = [
           'draw'=> $request->getParam('draw'), // ajax请求次数，作为标识符
           'recordsTotal'=>User::count(),
           'recordsFiltered'=>$count_filtered,
           'data'=>$data,
        ];
        return json_encode($info,true);
	}
}

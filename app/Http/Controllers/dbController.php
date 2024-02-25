<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use App\Models\donor;
use App\Models\recipient;
use App\Models\blood;
use App\Models\campaign;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;


class dbController extends Controller
{
    function dbDonor()
    {
        $data = donor::paginate(5);
        return view('donorTable',['donors'=>$data]);
    }

    function dbRecipient()
    {
        $data = recipient::paginate(5);
        return view('recipientTable',['recipients'=>$data]);
    }

    
    function showBloodTable()
    {
        $data = DB::table('bloods')
                ->join('recipients','recipients.recipient_adhaar_no','=','bloods.recipient_adhaar_no')
                ->where('bloods.availablity','=',0)
                ->get();
        return view('allotedBlood',['bloods'=>$data]);
    }

    function addBlood(Request $req)
    {
        $donor = donor::where('donor_adhaar_no','=',$req->donorId)->first();
        
        $donationData = Carbon::create($req->date);
        $expiryDate=$donationData->addDays(42);
        $lastDonated = Carbon::create($req->date);

        $tempLast = $lastDonated->subMonths(6);
        
        if($donor)
        {
            if($tempLast>$donor->last_donated)
            {
                    for($count=0;$count<$req->unit;$count++)
                {
                    $blood = new blood;
                    $blood->availablity=1;
                    $blood->valid_upto=$expiryDate;
                    $blood->donor_adhaar_no =$req->donorId;
                    $blood->blood_group=$donor->blood_group;
                    $blood->d_date=$req->date;
                    $blood->campaign_id=$req->campId;
                    $blood->save();
                }

                $donor->last_donated=$req->date;
                $donor->save();
                return back()->with('bloodAddSuccess','Blood added succesfully.');
            }
            else
            {
                return back()->with('bloodAddFail','Donor can only donate after 6 months from previous donation!');
            }
            
        }
        else{
            return back()->with('bloodAddFail','Donor not found!');
        }
    }
    function addDonor(Request $req)//success message handing pending here
    {

        
        if(Donor::where('donor_adhaar_no','=',$req->adhaar_no)->exists())
        {
                return back()->with('donorFailed','Donor already exist!');
        }
        if(!$req->donorAdhaarFile)
        {
            return back()->with('donorFailed','Adhaar file not found!');
        }

        $donor = new donor;
        $donor->donor_adhaar_no = $req->adhaar_no;
        $donor->name            = $req->name;
        $donor->email           = $req->email;
        $donor->phone           = $req->phone;
        $donor->address         = $req->address;
        $donor->dob             = $req->dob;
        $donor->gender          = $req->gender;
        $donor->weight          = $req->weight;
        $donor->blood_group     = $req->bloodGroup;
        
        


        $adhaarExtension= $req->file('donorAdhaarFile')->extension();
        $adhaarFileName=time()."-".$req->adhaar_no.".".$adhaarExtension;
        $adhaarUrl=$req->donorAdhaarFile->storeAs('uploads/donor/adhaarCard',$adhaarFileName);

        $donor->adhar_file_path=$adhaarUrl;

        $affacted=$donor->save();
        if($affacted)
        {
            return back()->with('donorAdded','Donor added successfully.');
        }
        else
        {
            return back()->with('donorFailed','Something went wrong!');
        }
        
    }
    function addRecipient(Request $req)
    {

        
        if(Recipient::where('recipient_adhaar_no','=',$req->adhaar_no)->exists())
        {
            return back()->with('fail','Recipient already exist!');
        }
        $recipient = new recipient;
        $recipient->recipient_adhaar_no = $req->adhaar_no;
        $recipient->name                = $req->name;
        $recipient->email               = $req->email;
        $recipient->phone               = $req->contact;
        $recipient->address             = $req->address;
        $recipient->dob                 = $req->dob;
        $recipient->gender              = $req->gender;
        $recipient->weight              = $req->weight;
        $recipient->blood_group         = $req->bloodGroup;

        if(!$req->has('adhaarFile'))
            {
                return back()->with('fail','Adhaar File not found');
            }

        if(!$req->has('presFile'))
        {
            return back()->with('fail','Prescription File not found');
        }

        

        $adhaarExtension= $req->file('adhaarFile')->extension();
        $adhaarFileName=time()."-".$req->adhaar_no.".".$adhaarExtension;
        $adhaarUrl=$req->adhaarFile->storeAs('uploads/adhaarCard',$adhaarFileName);


        $presExtension= $req->file('presFile')->extension();
        $presFileName=time()."-".$req->adhaar_no.".".$presExtension;
        $presUrl=$req->presFile->storeAs('uploads/prescriptionFiles',$presFileName);
        

        

        $recipient->prescription_file_path=$presUrl;
        $recipient->adhaar_file_path=$adhaarUrl;
        
        
        $affected=$recipient->save();
        if($affected)
        {
            return back()->with('recipientAdded','Recipient added successfully.');
        }
       else
       {
           return back()->with('fail','Oops something went wrong!');
       }
        
        

    }

/* ..............................................................Manage Blood .......................................*/
    function dbBlood()
    {
        $data = blood::paginate(5);
        return view('bloodTable',['bloods'=>$data]);
    }

    function showBlood(Request $req)//blood table page
    {
        $data = blood::where('blood_group',$req->blood_group
        )
        ->where('availablity','=',1)
        ->get();
        return view('bloodTable',['bloods'=>$data]);
    }
    function delBlood(Request $req)
    {    
            $res= Blood::where('id',$req->id)->delete();
            return back();    
    }


    function allotBlood(Request $req)
    {
       
        
        
        
        
        if(Recipient::where('recipient_adhaar_no','=',$req->r_id)->exists())
        {
            if(Donor::where('donor_adhaar_no','=',$req->r_id)->exists())
            {
                return back()->with('allotFail','Recipient and donor are same!');
            }
            $recipient = Recipient::where('recipient_adhaar_no','=',$req->r_id)->first();
            $bloodCount = Blood::where('blood_group','=',$recipient->blood_group)
                            ->where('availablity','=',1)
                            ->where('valid_upto','>',$req->date)
                            ->count();
                            
            $bloods = Blood::where('blood_group','=',$recipient->blood_group)
                        ->where('availablity','=',1)->get();
            if($bloodCount>=$req->unit)
                {
                    $affected=null;
                    $count = $req->unit;
                    foreach( $bloods as $blood)
                    {
                        if($count==0)
                        {
                            break;
                        }
                        $blood->availablity=0;
                        $blood->recipient_adhaar_no=$req->r_id;
                        $blood->r_date=$req->date;
                        $affected=$blood->save();
                        $count--;
 
                    }
                    if($affected)
                    {
                        return back()->with('allotSuccess','Blood is alloted successfully');
                    }
                    else
                    {
                        return back()->with('allotFail','Oops Something went wrong');
                    }
                    


                }
            else
            {
                return back()->with('allotFail','Blood is not available');
            }

        }
        else{
            return back()->with('allotFail','Recipient not found');
        }
    }

    //.........................Recipient.........................
    function delRec(Request $req)
    {

        
        if (DB::table('bloods')->where('recipient_adhaar_no', $req->adhaarNo)->exists()) {
            
            return back()->with('fail','Recipient alloted for blood cannot be deleted!');
        }
        else{
            $res= Recipient::where('recipient_adhaar_no',$req->adhaarNo)->delete();
            return back();
        }
    }

    // ..........................campaign...............................................
    function showCampBlood()
    {
        $data = DB::table('campaigns')
                ->join('bloods','bloods.campaign_id','=','campaigns.id')
                ->join('donors','donors.donor_adhaar_no','=','bloods.donor_adhaar_no')
                ->get();

                return view('campaignBloodTable',['bloods'=>$data]);
    }

    function showCampTable()
    {
        $campTable= DB::table('campaigns')->get();
        
        return view('campaignTable',['camps'=>$campTable]);
    }
    function addCampaign(Request $req)
    {
        // $req->validate([
        //     'c_name'=>'required|unique:campaigns'
        // ]);
        if(campaign::where('c_name',$req->c_name)->exists())
        {
            return back()->with('campAddedFail','Campaign name has already been taken!');
        }
        $camp= new campaign;
        $camp->c_name=$req->c_name;
        $camp->c_date=$req->c_date;
        $camp->c_location=$req->c_location;

        $affacted=$camp->save();
        if($affacted)
        {
            return back()->with('campAdded','Campaign added successfuly!');
        }
        else
        {
            return back()->with('campAddedFail','Something went wrong!');
        }

    }
    function bloodStatus(Request $req)
    {
        
        if(Blood::where('blood_group','=',$req->blood_group)->exists())
        {
            return back()->with('bloodFound',' found.Reach our nearest hospital.');
        }
        else
        {
            return back()->with('bloodNotFound',' found.Reach our nearest hospital.');
        }
    }
}
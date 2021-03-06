<?php

namespace App\Models; // modelsディレクトリに移動させたので

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    // ホワイトリスト： $fillableに指定したカラムのみ、create()やfill()、update()で値が代入される。
    // $contact->update($request->all()); <- $fillableに指定していないもの以外は入らない。
    protected $fillable = [
        'first_name', 'last_name', 'email', 'city', 'country', 'job_title', 'file_name', 'pref_id',
    ];
    // ブラックリスト：$guardedに指定したカラムのみ、create()やfill()、update()で値が代入されない。
    // $contact->update($request->all()); <- $guardedに指定していないものは全て入り得る
    // protected $guarded = [];

    // public function prefecture(){
    //     return $this->belongsTo('App\Models\Prefecture');
    // }
    public function getPrefNameAttribute() {
        return \App\Models\Prefecture::where('id', $this->pref_id)->first()->name;
    }
}

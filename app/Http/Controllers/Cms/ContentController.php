<?php
namespace App\Http\Controllers\Cms;
use App\Http\Controllers\Controller;
use App\Models\PromoContent;
use Illuminate\Http\Request;

class ContentController extends Controller
{
    public function index(){
        $items = PromoContent::orderBy('display_order')->paginate(20);
        return view('cms.index', compact('items'));
    }
    public function create(){ return view('cms.form', ['item'=>new PromoContent]); }
    public function store(Request $r){
        $data = $r->validate([
            'title'=>'required|string',
            'media_type'=>'required|in:image,video',
            'file'=>'required|file',
            'is_active'=>'sometimes|boolean',
            'display_order'=>'nullable|integer',
            'starts_at'=>'nullable|date',
            'ends_at'=>'nullable|date|after_or_equal:starts_at',
        ]);
        $path = $r->file('file')->store('promos','public');
        PromoContent::create([
            'title'=>$data['title'],
            'media_type'=>$data['media_type'],
            'file_path'=>$path,
            'is_active'=>$r->has('is_active') ? $r->boolean('is_active') : true,
            'display_order'=>$data['display_order'] ?? 0,
            'starts_at'=>$data['starts_at'] ?? null,
            'ends_at'=>$data['ends_at'] ?? null,
        ]);
        return redirect()->route('cms.contents.index')->with('ok','Konten tersimpan');
    }
    public function edit(PromoContent $content){ return view('cms.form', ['item'=>$content]); }
    public function update(Request $r, PromoContent $content){
        $data = $r->validate([
            'title'=>'required|string',
            'media_type'=>'required|in:image,video',
            'file'=>'nullable|file',
            'is_active'=>'sometimes|boolean',
            'display_order'=>'nullable|integer',
            'starts_at'=>'nullable|date',
            'ends_at'=>'nullable|date|after_or_equal:starts_at',
        ]);
        if($r->hasFile('file')){ $data['file_path'] = $r->file('file')->store('promos','public'); }
        $data['is_active'] = $r->boolean('is_active');
        $content->update($data);
        return back()->with('ok','Konten diperbarui');
    }
    public function destroy(PromoContent $content){ $content->delete(); return back()->with('ok','Konten dihapus'); }
}

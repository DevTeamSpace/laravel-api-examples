<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\Models\Document;
use App\Jobs\SendFileToBoxViewer;
use Validator;
use Storage;

class DocumentsController extends Controller
{
    /**
     * Display a listing of the resource.
     * @param string
     * @param integer
     * @return \Illuminate\Http\Response
     */
    public function index($category, $id)
    {
        return Document::where('category', $category)->where('categoryId', $id)->get();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $input = $request->all();
        $categoryId = $input['categoryId'];
        $category = $input['category'];
        $unique = isset($input['unique']) ? true : false;
        $file = $input['file'];
        $fileName = $file->getClientOriginalName();
        $name = $input['name'];
        $path = 'member/' . $category . '/' . $categoryId . '/' . $fileName;
        $rules = array('file' => 'required|mimes:pdf');
        $validator = Validator::make(array('file' => $file), $rules);
        if ($validator->passes()) {
            Storage::put(
                $path,
                file_get_contents($file->getRealPath())
            );
            $this->dispatch(new SendFileToBoxViewer($path, $input['author'], $name, 'pdf', $category, $categoryId, null, $unique));
            return $this->respondOK('file uploaded');
        }
        return $this->errorInternalError('file not uploaded, failed to pass validation');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        return response()->json(forRestmod($this->repo->find($id), 'stock'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $input = $request->all();
        if($this->repo->update($input, $id)){
            return $this->respondOK("resource updated");
        }else{
            return $this->errorNotFound('resource not updated');
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $doc = Document::where('id', $id)->first();
        $doc->delete();
    }
}

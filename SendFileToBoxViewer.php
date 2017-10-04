<?php

namespace App\Jobs;

use Carbon\Carbon;
use Illuminate\Contracts\Bus\SelfHandling;
use Box\View\Client;
use Storage;
use App\Models\Document;

class SendFileToBoxViewer extends Job implements SelfHandling
{
    private $filePath;
    private $author;
    private $type;
    private $name;
    private $category;
    private $categoryId;
    private $expiresAt;
    private $unique;

    /**
     * Create a new job instance.
     * @param string
     * @param string
     * @param string
     * @param string
     * @param string
     * @param int
     * @param /Carbon/Carbon
     * @param boolean
     */
    public function __construct($filePath = null, $author = null, $name = '', $type = '', $category = '', $categoryId = 0, $expiresAt = null, $unique = false)
    {
        $this->filePath = $filePath;
        $this->author = $author;
        $this->type = $type;
        $this->name = $name;
        $this->category = $category;
        $this->categoryId = $categoryId;
        if(!$expiresAt){
            $this->expiresAt =  Carbon::createFromDate(2215, 12, 25);
        }else{
            $this->expiresAt = $expiresAt;
        }
        $this->unique = $unique;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $file = null;
        $boxView = new Client(env('BOX_VIEW_API_KEY'));
        if (Storage::exists($this->filePath)) {
            $handle = fopen(storage_path('app/' . $this->filePath), 'r');
            $file = $boxView->uploadFile($handle, ['name' => $this->name]);
        }
        $session = $file->createSession([
            'expiresAt' => $this->expiresAt,
            'isDownloadable' => true,
            'isTextSelectable' => false,
        ]);
        $document = null;
        if($this->unique){
            $document = Document::where('category', $this->category)->where('categoryId', $this->categoryId)->first();
        }else {
            $document = Document::where('path', $this->filePath)->first();
        }
        if (!$document) {
            $document = new Document();
        }

        $document->documentId = $file->id();
        $document->status = $file->status();
        $document->author = $this->author;
        $document->name = $this->name;
        $document->type = $this->type;
        $document->category = $this->category;
        $document->categoryId = $this->categoryId;
        $document->path = $this->filePath;
        $document->expiresAt = $this->expiresAt;
        $document->viewUrl = $session->viewUrl();
        $document->assetsUrl = $session->assetsUrl();
        $document->realtimeUrl = $session->realtimeUrl();
        $document->save();
    }
}

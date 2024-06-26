<?php

namespace App\Http\Livewire;

use App\Models\Inputvector;
use App\Models\Pdfdoc;
use App\Models\TextData;
use App\Models\TextVector;
use App\Services\VectorService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use OpenAI\Laravel\Facades\OpenAI;
use Sastrawi\Stemmer\StemmerFactory;

class ConvertFiletoText extends Component
{
    public $document, $convertedText, $input , $answer;
    protected $rules = [
        'document' => 'required',
        'input' => 'required'
    ];
    public function convertFile()
    {
        $this->validate();
        $pdf_file = Pdfdoc::find($this->document);
        try {
            //convert  input into vector
            $vector = OpenAI::embeddings()->create([
                'model' => 'text-embedding-ada-002',
                'input' => $this->input,
            ]);
            // Instantiate the VectorService class
            $vectorService = new VectorService();;;
            $relevantChunks = $vectorService->getMostSimilarVectors($vector['data'][0]['embedding'], $pdf_file->id, 4);
            ///Store Input and Vector
            $similarTexts = $vectorService->getTextsFromIds(array_column($relevantChunks, 'id'));
            // Combine the relevant texts into a single string as the knowledge base
            $knowledgeBase = implode(' ', $similarTexts);            
           
            // Construct the prompt as a question and knowledge base
            $prompt = "You are an expert in answering Questions \n please answer the following question: " . $this->input . " Using only the following source documents : " . $knowledgeBase ."\n if the answer is not there just say 'sorry I do not Know'" ;

            // Ask the model a question based on the prompt
            $response = OpenAI::completions()->create([
                'model' => 'gpt-3.5-turbo-instruct',
                'prompt' => $prompt,
                'max_tokens' => 1000, // Adjust the max tokens as needed
            ]);
            //dd($response['choices'][0]['text']);
            $this->answer =  $response['choices'][0]['text'];
            
        } catch (\Throwable $th) {
            dd($th->getMessage());
        }
    }

    public function render()
    {
        $docs = Pdfdoc::all();
        return view('livewire.convert-fileto-text', ['docs' => $docs])->extends('layouts.app')->section('content');
    }
}

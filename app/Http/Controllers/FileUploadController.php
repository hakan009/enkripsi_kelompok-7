<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ECC;
use App\Models\Upload;
use Illuminate\Support\Facades\Storage;

class FileUploadController extends Controller
{
    protected $ecc;

    public function __construct(ECC $ecc)
    {
        $this->ecc = $ecc;
    }

    public function listUploads()
    {
        $uploads = Upload::all();
        return view('list', compact('uploads'));
    }

    public function upload(Request $request)
    {
        // Validasi inputan file
        $request->validate([
            'file' => 'required|file|max:10240',
        ]);

        // Ambil file dari request
        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $fileExtension = $file->getClientOriginalExtension();

        // Baca isi file
        $fileContent = file_get_contents($file->getRealPath());

        // Enkripsi isi file menggunakan ECC
        $privateKey = $this->ecc->generatePrivateKey();
        $publicKey = $this->ecc->generatePublicKey($privateKey);
        $encryptedContent = $this->ecc->encrypt($fileContent, $publicKey);

        // Simpan file terenkripsi ke direktori
        $encryptedFileName = base64_encode($this->ecc->encrypt(pathinfo($originalName, PATHINFO_FILENAME), $publicKey));
        $path = 'public/uploads/' . $encryptedFileName;
        Storage::put($path, $encryptedContent);

        // Simpan informasi file ke database
        $upload = new Upload();
        $upload->original_name = $originalName;
        $upload->encrypted_name = $encryptedFileName; // Simpan nama file terenkripsi tanpa ekstensi
        $upload->file_path = $path;
        $upload->public_key = $publicKey;
        $upload->private_key = $privateKey;
        $upload->save();

        // Redirect ke halaman list uploads
        return redirect()->route('uploads.list')->with('success', 'File uploaded and encrypted successfully!');
    }

    public function decryptFile($id)
    {
        // Ambil informasi file dari database berdasarkan ID
        $upload = Upload::findOrFail($id);

        // Ambil path file terenkripsi dari database
        $encryptedFilePath = storage_path('app/' . $upload->file_path);

        // Baca isi file terenkripsi
        $encryptedContent = Storage::get($upload->file_path);

        // Dekripsi isi file menggunakan ECC
        $decryptedContent = $this->ecc->decrypt($encryptedContent, $upload->private_key);

        // Dekripsi nama file asli
        $originalFileName = $this->ecc->decrypt(base64_decode($upload->encrypted_name), $upload->private_key) . '.' . $upload->original_name;

        // Mengunduh file dengan nama asli
        return response()->streamDownload(function () use ($decryptedContent) {
            echo $decryptedContent;
        }, $originalFileName);
    }

    public function downloadEncryptedFile($id)
    {
        // Retrieve the file information from the database based on the ID
        $upload = Upload::findOrFail($id);

        // Get the path to the encrypted file from the database
        $encryptedFilePath = storage_path('app/' . $upload->file_path);

        // Check if the file exists
        if (!file_exists($encryptedFilePath)) {
            return redirect()->route('uploads.list')->with('error', 'Encrypted file not found.');
        }

        // Provide the encrypted file for download
        return response()->download($encryptedFilePath, $upload->encrypted_name);
    }
}
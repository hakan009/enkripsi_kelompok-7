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
        // Validate file input
        $request->validate([
            'file' => 'required|file|max:10240',
        ]);

        // Get the file from the request
        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $fileExtension = $file->getClientOriginalExtension();

        // Read file content
        $fileContent = file_get_contents($file->getRealPath());

        // Encrypt the file
        $privateKey = $this->ecc->generatePrivateKey();
        $publicKey = $this->ecc->generatePublicKey($privateKey);

        $encryptedContent = $this->ecc->encrypt($fileContent, $publicKey);

        // Generate encrypted file name without the extension
        $encryptedFileName = base64_encode($this->ecc->encrypt(pathinfo($originalName, PATHINFO_FILENAME), $publicKey));
        $path = 'public/uploads/' . $encryptedFileName;

        // Save encrypted file to directory
        Storage::put($path, $encryptedContent);

        // Save file information to the database
        $this->ecc->saveFileInfo(
            $originalName,
            $encryptedFileName, // Save without file extension
            $path,
            $publicKey,
            $privateKey
        );

        // Redirect to the list uploads page
        return redirect()->route('uploads.list')->with('success', 'File uploaded and encrypted successfully!');
    }

    public function decryptFile($id)
    {
        // Retrieve the file information from the database
        $upload = Upload::findOrFail($id);

        // Extract the necessary information
        $encryptedName = $upload->encrypted_name;
        $publicKey = $upload->public_key;
        $privateKey = $upload->private_key;
        $path = $upload->file_path;

        // Read the encrypted file content from storage
        $encryptedContent = Storage::get($path);

        // Initialize the ECC service
        $ecc = new ECC();

        // Decrypt the content using the private key
        $decryptedContent = $ecc->decrypt($encryptedContent, $privateKey);

        // Generate the original file name
        $originalFileName = base64_decode($encryptedName);
        $fileExtension = pathinfo($originalFileName, PATHINFO_EXTENSION);

        // Set the appropriate MIME type based on file extension
        $mimeType = Storage::mimeType($path) ?? 'application/octet-stream';

        // Return the decrypted file as a download response
        return response($decryptedContent)
            ->header('Content-Type', $mimeType)
            ->header('Content-Disposition', 'attachment; filename="' . $originalFileName . '"');
    }
}

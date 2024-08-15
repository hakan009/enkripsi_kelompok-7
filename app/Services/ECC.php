<?php

namespace App\Services;

use App\Models\Upload;

class ECC
{
    private $a = -1;
    private $b = 1;
    private $p = 23; // Angka prima untuk modulo operasi

    // Generate private key
    public function generatePrivateKey()
    {
        return random_int(1, $this->p - 1);
    }

    // Generate public key
    public function generatePublicKey($privateKey)
    {
        $G = $this->generateBasePoint();
        $publicKey = $this->scalarMultiplication($privateKey, $G);
        return implode(',', $publicKey);
    }

    // Generate base point
    private function generateBasePoint()
    {
        return [3, 10]; // Titik G di kurva
    }

    // Scalar multiplication
    private function scalarMultiplication($k, $P)
    {
        $R = [0, 0];
        $Q = $P;
        while ($k > 0) {
            if ($k % 2 == 1) {
                $R = $this->pointAddition($R, $Q);
            }
            $Q = $this->pointAddition($Q, $Q);
            $k = intdiv($k, 2);
        }
        return $R;
    }

    // Point addition
    private function pointAddition($P, $Q)
    {
        if ($P == [0, 0]) return $Q;
        if ($Q == [0, 0]) return $P;

        [$x1, $y1] = $P;
        [$x2, $y2] = $Q;

        if ($x1 == $x2 && $y1 == -$y2) return [0, 0];

        if ($P != $Q) {
            $m = ($y2 - $y1) * $this->modInverse($x2 - $x1, $this->p) % $this->p;
        } else {
            $m = (3 * $x1 ** 2 + $this->a) * $this->modInverse(2 * $y1, $this->p) % $this->p;
        }

        $x3 = ($m ** 2 - $x1 - $x2) % $this->p;
        $y3 = ($m * ($x1 - $x3) - $y1) % $this->p;

        return [$x3, $y3];
    }

    // Modulo inverse
    private function modInverse($a, $p)
    {
        return $this->extendedGcd($a, $p)[1] % $p;
    }

    // Extended GCD
    private function extendedGcd($a, $b)
    {
        if ($b == 0) return [$a, 1, 0];
        [$g, $x, $y] = $this->extendedGcd($b, $a % $b);
        return [$g, $y, $x - intdiv($a, $b) * $y];
    }

    public function encrypt($message, $publicKey)
    {
        // Convert message to integer
        $m = intval(bin2hex($message), 16);
        $pubKey = explode(',', $publicKey);
        $x = $pubKey[0];
        $y = $pubKey[1];
        $c1 = $this->scalarMultiplication($m, [$x, $y]);

        // Return encrypted message as a string
        return json_encode($c1);
    }

    public function decrypt($encryptedMessage, $privateKey)
    {
        // Convert encrypted message from JSON
        $c1 = json_decode($encryptedMessage, true);

        // The decryption logic should be tailored to your specific encryption scheme
        // Assuming $c1[0] is the integer representation of the encrypted content
        $c1Int = $c1[0];

        // Convert integer to hexadecimal string
        $hexMessage = dechex($c1Int);

        // Ensure the hexadecimal string has an even length
        if (strlen($hexMessage) % 2 != 0) {
            $hexMessage = '0' . $hexMessage;
        }

        // Decrypt the message
        $decryptedMessage = hex2bin($hexMessage);

        return $decryptedMessage;
    }


    public function saveFileInfo($originalName, $encryptedName, $path, $publicKey, $privateKey)
    {
        return Upload::create([
            'original_name' => $originalName,
            'encrypted_name' => $encryptedName,
            'file_path' => $path,
            'public_key' => $publicKey,
            'private_key' => $privateKey,
        ]);
    }
}

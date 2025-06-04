<?php
// src/PasswordCracker.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/AttackType.php';

class PasswordCracker
{
    private PDO $pdo;
    private array $hashedPasswordsFromDb;
    private int $currentJobId = 0;
    private int $totalHardMixedCombinations;

    public function __construct()
    {
        $this->pdo = getDbConnection();
        $this->loadHashedPasswordsFromDb();
        $this->totalHardMixedCombinations = pow(62, 6); // (a-z, A-Z, 0-9) -> 26+26+10 = 62 characters
    }

    private function loadHashedPasswordsFromDb(): void
    {
        $stmt = $this->pdo->query("SELECT user_id, password as password_hash FROM not_so_smart_users");
        $this->hashedPasswordsFromDb = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /**
     * Sets the ID of the current job.
     * @param int $jobId
     */
    public function setCurrentJobId(int $jobId): void
    {
        $this->currentJobId = $jobId;
    }

    public function crack(AttackType $attackType, string $lastCheckedCombination = null): array
    {
        $crackedPasswords = [];
        $message = "Unknown attack type or error during cracking.";
        $status = 'error';
        $expectedCount = $attackType->expectedCount();
        $tempHashedPasswords = $this->hashedPasswordsFromDb;

        switch ($attackType) {
            case AttackType::EasyNumbers:
                $crackedPasswords = $this->crackEasyNumbers($tempHashedPasswords, $expectedCount);
                $message = "Found " . count($crackedPasswords) . " out of " . $expectedCount . " easy (number) passwords.";
                break;
            case AttackType::MediumDictionary:
                $crackedPasswords = $this->crackMediumDictionary($tempHashedPasswords, $expectedCount);
                $message = "Found " . count($crackedPasswords) . " out of " . $expectedCount . " medium dictionary passwords.";
                break;
            case AttackType::MediumAlphaNum:
                $crackedPasswords = $this->crackMediumAlphaNum($tempHashedPasswords, $expectedCount);
                $message = "Found " . count($crackedPasswords) . " out of " . $expectedCount . " medium alphanumeric passwords.";
                break;
            case AttackType::HardMixed:
                $crackedPasswords = $this->crackHardMixed($tempHashedPasswords, $expectedCount, $lastCheckedCombination);
                $message = "Found " . count($crackedPasswords) . " out of " . $expectedCount . " hard mixed passwords.";
                break;
        }

        // Status 'success' if something is found, or if it's an attack type where an empty result is not a failure.
        if (!empty($crackedPasswords) ||
            $attackType === AttackType::EasyNumbers ||
            $attackType === AttackType::MediumAlphaNum ||
            $attackType === AttackType::MediumDictionary) {
            $status = 'success';
        }

        return [
            'status' => $status,
            'message' => $message,
            'results' => [$attackType->value => $crackedPasswords]
        ];
    }

    private function crackEasyNumbers(array &$hashedPasswordsToSearch, int $expectedCount): array
    {
        $foundCount = 0;
        $passwordsCracked = [];
        if (empty($hashedPasswordsToSearch) || $expectedCount === 0) return [];

        $flippedDbHashes = array_flip($hashedPasswordsToSearch);
        for ($i = 0; $i <= 99999; $i++) {
            if ($foundCount >= $expectedCount && $expectedCount > 0) break;
            $possiblePassword = str_pad((string)$i, 5, '0', STR_PAD_LEFT);
            $hashedPossiblePassword = salter($possiblePassword);
            if (isset($flippedDbHashes[$hashedPossiblePassword])) {
                $userId = $flippedDbHashes[$hashedPossiblePassword];
                $passwordsCracked[$userId] = $possiblePassword;
                $foundCount++;
                unset($hashedPasswordsToSearch[$userId], $flippedDbHashes[$hashedPossiblePassword]);
            }
        }
        return $passwordsCracked;
    }

    private function crackMediumDictionary(array &$hashedPasswordsToSearch, int $expectedCount): array
    {
        $foundCount = 0;
        $passwordsCracked = [];
        if (empty($hashedPasswordsToSearch) || $expectedCount === 0) return [];

        $dictionaryFilePath = __DIR__ . '/dictionary.txt';
        if (!file_exists($dictionaryFilePath)) {
            error_log("Dictionary file not found: " . $dictionaryFilePath);
            return [];
        }

        $flippedDbHashes = array_flip($hashedPasswordsToSearch);
        $fileHandle = fopen($dictionaryFilePath, 'r');
        if ($fileHandle) {
            while (($line = fgets($fileHandle)) !== false) {
                if ($foundCount >= $expectedCount && $expectedCount > 0) break;
                $word = trim(strtolower($line));
                if (strlen($word) > 6 || !ctype_alpha($word)) continue;

                $hashedPossiblePassword = salter($word);
                if (isset($flippedDbHashes[$hashedPossiblePassword])) {
                    $userId = $flippedDbHashes[$hashedPossiblePassword];
                    $passwordsCracked[$userId] = $word;
                    $foundCount++;
                    unset($hashedPasswordsToSearch[$userId], $flippedDbHashes[$hashedPossiblePassword]);
                }
            }
            fclose($fileHandle);
        }
        return $passwordsCracked;
    }

    private function generateMediumAlphaNumPasswords(): Generator
    {
        $uppercaseLetters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $digits = '0123456789';
        for ($i = 0; $i < strlen($uppercaseLetters); $i++) {
            for ($j = 0; $j < strlen($uppercaseLetters); $j++) {
                for ($k = 0; $k < strlen($uppercaseLetters); $k++) {
                    for ($l = 0; $l < strlen($digits); $l++) {
                        yield $uppercaseLetters[$i] . $uppercaseLetters[$j] . $uppercaseLetters[$k] . $digits[$l];
                    }
                }
            }
        }
    }

    private function crackMediumAlphaNum(array &$hashedPasswordsToSearch, int $expectedCount): array
    {
        $foundCount = 0;
        $passwordsCracked = [];
        if (empty($hashedPasswordsToSearch) || $expectedCount === 0) return [];

        $flippedDbHashes = array_flip($hashedPasswordsToSearch);
        foreach ($this->generateMediumAlphaNumPasswords() as $possiblePassword) {
            if ($foundCount >= $expectedCount && $expectedCount > 0) break;
            $hashedPossiblePassword = salter($possiblePassword);
            if (isset($flippedDbHashes[$hashedPossiblePassword])) {
                $userId = $flippedDbHashes[$hashedPossiblePassword];
                $passwordsCracked[$userId] = $possiblePassword;
                $foundCount++;
                unset($hashedPasswordsToSearch[$userId], $flippedDbHashes[$hashedPossiblePassword]);
            }
        }
        return $passwordsCracked;
    }

    private function generateMixedPasswords(?string $startFromCombination = null): Generator
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $len = strlen($chars);
        $charMap = array_flip(str_split($chars));
        $c = array_fill(0, 6, 0); // Indices for 'aaaaaa'

        if ($startFromCombination !== null && strlen($startFromCombination) === 6) {
            $temp_c = array_fill(0, 6, 0);
            $validStart = true;
            for ($i = 0; $i < 6; $i++) {
                if (isset($charMap[$startFromCombination[$i]])) {
                    $temp_c[$i] = $charMap[$startFromCombination[$i]];
                } else {
                    error_log("Invalid character in startFromCombination for generateMixedPasswords: " . $startFromCombination . ". Starting with 'aaaaaa'.");
                    $validStart = false;
                    break;
                }
            }
            if ($validStart) $c = $temp_c;
        }

        do {
            $currentCombination = '';
            for ($i = 0; $i < 6; $i++) $currentCombination .= $chars[$c[$i]];
            yield $currentCombination;

            $j = 5;
            while ($j >= 0) {
                $c[$j]++;
                if ($c[$j] < $len) break;
                $c[$j] = 0;
                $j--;
            }
            if ($j < 0) break; // All combinations exhausted
        } while (true);
    }

    private function crackHardMixed(array &$hashedPasswordsToSearch, int $expectedCount, ?string $startFromCombination = null): array
    {
        $foundCount = 0;
        $passwordsCracked = [];
        if (empty($hashedPasswordsToSearch) || $expectedCount === 0) return [];

        $flippedDbHashes = array_flip($hashedPasswordsToSearch);
        $processedCombinations = 0; // Counter for combinations checked in the current call
        $lastProgressUpdateTime = 0;

        $passwordGenerator = $this->generateMixedPasswords($startFromCombination);

        foreach ($passwordGenerator as $possiblePassword) {
            if ($foundCount >= $expectedCount && $expectedCount > 0) break;
            $processedCombinations++;

            // Periodically save progress (every 100,000 combinations or every 10 seconds)
            if ($this->currentJobId > 0 && ($processedCombinations % 100000 === 0 || (time() - $lastProgressUpdateTime > 10))) {
                $currentProgressEstimate = 0;
                if ($this->totalHardMixedCombinations > 0) {
                    // Note: This progress calculation is approximate if the attack is resumed.
                    // It does not precisely account for how many combinations were before $startFromCombination.
                    // However, it shows progress *within the current processing session*.
                    // last_checked_combination is a more important indicator for resumption.
                    $currentProgressEstimate = ($processedCombinations / $this->totalHardMixedCombinations) * 100;
                }
                $currentProgressEstimate = min(100.00, max(0.00, $currentProgressEstimate));
                $this->updateJobProgress($this->currentJobId, $currentProgressEstimate, $possiblePassword);
                $lastProgressUpdateTime = time();
            }

            $hashedPossiblePassword = salter($possiblePassword);
            if (isset($flippedDbHashes[$hashedPossiblePassword])) {
                $userId = $flippedDbHashes[$hashedPossiblePassword];
                $passwordsCracked[$userId] = $possiblePassword;
                $foundCount++;
                unset($hashedPasswordsToSearch[$userId], $flippedDbHashes[$hashedPossiblePassword]);
            }
        }
        return $passwordsCracked;
    }

    private function updateJobProgress(int $jobId, float $progress, ?string $lastCheckedCombination): void
    {
        if ($jobId <= 0) return;

        try {
            $stmt = $this->pdo->prepare(
                "UPDATE crack_jobs SET progress = :progress, last_checked_combination = :last_checked_combination, updated_at = NOW(), last_run_attempt = NOW() WHERE job_id = :job_id"
            );
            $stmt->bindValue(':progress', sprintf('%.2f', $progress));
            $stmt->bindValue(':last_checked_combination', $lastCheckedCombination);
            $stmt->bindValue(':job_id', $jobId, PDO::PARAM_INT);
            $stmt->execute();
        } catch (PDOException $e) {
            error_log("Failed to update progress for job ID $jobId: " . $e->getMessage());
        }
    }
}
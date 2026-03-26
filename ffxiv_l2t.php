<?php
declare(strict_types=1);

// FFXIV log parser for extracting and formatting chat and battle records from binary log files
class FFXIVLogParser
{
    // Mapping of chat and event type codes to human-readable names
    private const CHAT_TYPES = [
        0x03 => 'System',
        0x1D => 'Emote',
        0x29 => 'EnemyDamage',
        0x2A => 'AttackMiss',
        0x2B => 'Skill',
        0x2C => 'ItemUse',
        0x2D => 'Heal',
        0x2E => 'StatusGain',
        0x2F => 'StatusApply',
        0x30 => 'StatusRemove',
        0x31 => 'StatusExpire',
        0x39 => 'Notification',
        0x3A => 'BattleEvent',
        0x3C => 'SystemWarning',
        0x3D => 'NPCDialogue',
        0x3E => 'LootObtain',
        0x41 => 'LootRoll',
        0x44 => 'NPCDialogueAnnounce',
        0x48 => 'PartyFinder',
        0x40 => 'ExperienceGain',
        0xA9 => 'PlayerDamage',
        0xAA => 'EffectResist',
        0xAB => 'AbilityCast',
        0xAD => 'SelfHeal',
        0xAE => 'SelfStatusGain',
        0xAF => 'StatusEffect',
        0xB0 => 'SelfStatusEnd',
        0xB1 => 'StatusEnd',
        0xBA => 'Defeated',
        // chat
        0x0A => 'Say',
        0x0B => 'Shout',
        0x0E => 'Party',
        0x0F => 'Alliance',
        0x18 => 'FreeCompany',
        0x25 => 'CWLS1',
        0x65 => 'CWLS2',
        0x66 => 'CWLS3',
        0x67 => 'CWLS4',
        0x68 => 'CWLS5',
        0x69 => 'CWLS6',
        0x6A => 'CWLS7',
        0x6B => 'CWLS8',
    ];

    /**
     * Parse the given binary log file and extract all records.
     * Returns an array of parsed record arrays.
     */
    public function parseLogFile(string $filename): array
    {
        $data = file_get_contents($filename);
        if ($data === false) {
            throw new RuntimeException("The file cannot be read: {$filename}");
        }

        [$offsets, $payloadStart] = $this->readOffsetTable($data);

        $payloadSize = strlen($data) - $payloadStart;
        $usableOffsets = array_values(array_unique(
            array_filter($offsets, fn(int $o) => $o >= 0 && $o < $payloadSize)
        ));
        sort($usableOffsets);

        $results = [];
        $count = count($usableOffsets);
        for ($i = 0; $i < $count - 1; $i++) {
            $start = $payloadStart + $usableOffsets[$i];
            $end   = $payloadStart + $usableOffsets[$i + 1];

            $recordData = rtrim(substr($data, $start, $end - $start), "\x00");
            $record = $this->parseRecord($recordData);

            if ($record !== null) {
                $results[] = $record;
            }
        }

        if ($count > 0) {
            $blobStart = $payloadStart + $usableOffsets[$count - 1];
            $blob      = substr($data, $blobStart);
            foreach ($this->scanBlobForRecords($blob) as $r) {
                $results[] = $r;
            }
        }

        return $results;
    }

    /**
     * Read the offset table at the start of the log file.
     * Returns an array: [offsets array, payload start position].
     */
    private function readOffsetTable(string $data): array
    {
        if (strlen($data) < 8) {
            throw new RuntimeException('The file is too short to contain a valid offset table.');
        }
        $startIdx = (int) unpack('V', substr($data, 0, 4))[1];
        $endIdx   = (int) unpack('V', substr($data, 4, 4))[1];
        $recordCount = $endIdx - $startIdx;
        $payloadStart = 8 + $recordCount * 4;

        if ($recordCount === 0 || $payloadStart >= strlen($data)) {
            throw new RuntimeException('Cannot interpret the offset table. The file may be corrupted or not in the expected format.');
        }

        $offsets = array_values(unpack("V{$recordCount}", substr($data, 8, $recordCount * 4)));

        return [$offsets, $payloadStart];
    }

    /**
     * Parse a single record from binary data.
     * Returns an associative array or null if invalid.
     */
    private function parseRecord(string $recordData): ?array
    {
        if (strlen($recordData) < 8) {
            return null;
        }

        $timestampVal = unpack('V', substr($recordData, 0, 4))[1];
        if ($timestampVal < 1370000000 || $timestampVal > 2100000000) {
            return null;
        }

        $codeVal  = unpack('V', substr($recordData, 4, 4))[1];
        $channel  = $codeVal & 0xFF;
        $codeHex  = sprintf('%04X', $channel);

        $timestamp = date('Y-m-d H:i:s', $timestampVal);

        $payload = substr($recordData, 8);
        $fields  = $this->extractFields($payload);

        if (empty($fields)) {
            return null;
        }

        if (count($fields) === 1) {
            $player  = '';
            $message = $fields[0];
        } else {
            $player  = $fields[0];
            $message = implode(' ', array_slice($fields, 1));
        }

        if ($message === '') {
            return null;
        }

        $channelName = self::CHAT_TYPES[$channel] ?? "Unknown({$codeHex})";

        return [
            'timestamp'   => $timestamp,
            'channel'     => $channelName,
            'code'        => $codeHex,
            'player'      => $player,
            'message'     => $message,
        ];
    }

    /**
     * Scan a binary blob for valid records by searching for plausible timestamps and record markers.
     * Returns an array of parsed record arrays.
     */
    private function scanBlobForRecords(string $blob): array
    {
        $results = [];
        $len     = strlen($blob);

        $starts = [];
        for ($i = 0; $i + 9 <= $len; $i++) {
            $tsVal = unpack('V', substr($blob, $i, 4))[1];
            if ($tsVal < 1_370_000_000 || $tsVal > 2_100_000_000) {
                continue;
            }
            if (ord($blob[$i + 8]) !== 0x1F) {
                continue;
            }
            $starts[] = $i;
        }

        $n = count($starts);
        for ($j = 0; $j < $n; $j++) {
            $start      = $starts[$j];
            $end        = ($j + 1 < $n) ? $starts[$j + 1] : $len;
            $recordData = rtrim(substr($blob, $start, $end - $start), "\x00");
            $record     = $this->parseRecord($recordData);
            if ($record !== null) {
                $results[] = $record;
            }
        }

        return $results;
    }

    /**
     * Find the end position of a tag in the binary data, starting from pos.
     * Used for skipping over special tag sequences.
     */
    private function tagEnd(string $data, int $pos): int
    {
        $len = strlen($data);
        if ($pos + 3 > $len) {
            return $len;
        }
        $lenMarker = ord($data[$pos + 2]);
        if ($lenMarker >= 0x01 && $lenMarker < 0xF0) {
            $dataLen  = $lenMarker - 1;
            $endOfTag = $pos + 3 + $dataLen;
            if ($endOfTag < $len && ord($data[$endOfTag]) === 0x03) {
                return $endOfTag + 1;
            }
        }
        $end = strpos($data, "\x03", $pos + 1);
        return $end === false ? $len : $end + 1;
    }

    /**
     * Extract text fields from the payload by splitting on field separators and decoding.
     * Returns an array of decoded strings.
     */
    private function extractFields(string $payload): array
    {
        $chunks = [];
        $chunkStart = 0;
        $len = strlen($payload);
        $i = 0;

        while ($i < $len) {
            $c = ord($payload[$i]);

            if ($c === 0x02) {
                $chunks[] = substr($payload, $chunkStart, $i - $chunkStart);
                $i = $this->tagEnd($payload, $i);
                $chunkStart = $i;
                continue;
            }

            if ($c === 0x1f) {
                $chunks[] = substr($payload, $chunkStart, $i - $chunkStart);
                $chunkStart = $i + 1;
            }

            $i++;
        }

        if ($chunkStart < $len) {
            $chunks[] = substr($payload, $chunkStart);
        }

        $decoded = [];
        foreach ($chunks as $chunk) {
            $text = $this->decodeSeString($chunk);
            if ($text !== '') {
                $decoded[] = $text;
            }
        }

        return $decoded;
    }

    /**
     * Decode a single SE string chunk, removing tags and non-printable characters.
     * Returns a cleaned string.
     */
    private function decodeSeString(string $chunk): string
    {
        $visible = '';
        $len = strlen($chunk);
        $i = 0;

        while ($i < $len) {
            $c = ord($chunk[$i]);

            if ($c === 0x02) {
                $i = $this->tagEnd($chunk, $i);
                continue;
            }

            if ($c <= 0x1F || $c === 0x7F) {
                $i++;
                continue;
            }

            $visible .= $chunk[$i];
            $i++;
        }

        $text = mb_convert_encoding($visible, 'UTF-8', 'UTF-8');
        $cleaned = '';
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars !== false) {
            foreach ($chars as $char) {
                $cp = mb_ord($char, 'UTF-8');
                if ($cp < 0x20 || ($cp >= 0xE000 && $cp <= 0xF8FF)) {
                    continue;
                }
                $cleaned .= $char;
            }
        }

        $cleaned = preg_replace('/\s+/u', ' ', $cleaned);
        $cleaned = str_replace([' : ', ' , '], [':', ','], $cleaned);

        return trim($cleaned);
    }

    /**
     * Main method to parse a log file, write output, and print a preview.
     */
    public function parse(string $filePath): void
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException("The file cannot be found. : {$filePath}");
        }

        echo "Parsing: {$filePath}\n";

        $records = $this->parseLogFile($filePath);
        $lines   = $this->formatOutput($records);
        $outFile = $this->outputPath($filePath);

        file_put_contents($outFile, implode("\n", $lines) . "\n");

        echo 'Completed: ' . count($lines) . " items\n";
        echo "output: {$outFile}\n";
        echo "\nPreview:\n";
        foreach (array_slice($lines, 0, 10) as $line) {
            echo $line . "\n";
        }
    }

    /**
     * Generate output file path based on input log file name.
     */
    private function outputPath(string $inputFile): string
    {
        $dir  = dirname($inputFile);
        $base = pathinfo($inputFile, PATHINFO_FILENAME);
        return $dir . DIRECTORY_SEPARATOR . $base . '_parsed.txt';
    }

    /**
     * Format parsed records into human-readable log lines.
     * Returns an array of formatted strings.
     */
    private function formatOutput(array $records): array
    {
        $lines = [];
        foreach ($records as $r) {
            $lines[] = "[{$r['timestamp']}] [{$r['channel']}] [{$r['player']}] {$r['message']}";
        }
        return $lines;
    }
}

/**
 * Set the timezone from environment or system settings for consistent date formatting.
 */
function setTimezoneFromEnv(): void
{
    $tz = getenv('TZ');
    if ($tz !== false && $tz !== '') {
        date_default_timezone_set($tz);
        return;
    }
    if (PHP_OS_FAMILY === 'Darwin' && is_link('/etc/localtime')) {
        $target = readlink('/etc/localtime');
        if (preg_match('#zoneinfo/(.+)$#', $target, $m)) {
            date_default_timezone_set($m[1]);
            return;
        }
    }
    if (file_exists('/etc/timezone')) {
        $tz = trim(file_get_contents('/etc/timezone'));
        if ($tz !== '') {
            date_default_timezone_set($tz);
        }
    }
}

/**
 * Main entry point: parse command-line argument, process log file, and write output.
 */
function main(): void
{
    global $argv;

    setTimezoneFromEnv();

    if (count($argv) !== 2) {
        fwrite(STDERR, "Usage: php ffxiv_log_parser.php <logfile>\n");
        exit(1);
    }

    try {
        $parser = new FFXIVLogParser();
        $parser->parse($argv[1]);
    } catch (RuntimeException $e) {
        fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
        exit(1);
    }
}

main();

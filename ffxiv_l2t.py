#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import datetime as dt
import mmap
import os
import re
import struct
import sys


# FFXIV log parser for extracting and formatting chat and battle records from binary log files
class FFXIVLogParser:

    # Mapping of chat and event type codes to human-readable names
    CHAT_TYPES = {
        0x03: "System",
        0x1D: "Emote",

        0x29: "EnemyDamage",
        0x2A: "AttackMiss",
        0x2B: "Skill",
        0x2C: "ItemUse",
        0x2D: "Heal",
        0x2E: "StatusGain",
        0x2F: "StatusApply",
        0x30: "StatusRemove",
        0x31: "StatusExpire",

        0x39: "Notification",
        0x3A: "BattleEvent",
        0x3C: "SystemWarning",
        0x3D: "NPCDialogue",
        0x3E: "LootObtain",
        0x41: "LootRoll",
        0x44: "NPCDialogueAnnounce",
        0x48: "PartyFinder",

        0x40: "ExperienceGain",

        0xA9: "PlayerDamage",
        0xAA: "EffectResist",
        0xAB: "AbilityCast",
        0xAD: "SelfHeal",
        0xAE: "SelfStatusGain",
        0xAF: "StatusEffect",

        0xB0: "SelfStatusEnd",
        0xB1: "StatusEnd",
        0xBA: "Defeated",

        # chat
        0x0A: "Say",
        0x0B: "Shout",
        0x0E: "Party",
        0x0F: "Alliance",
        0x18: "FreeCompany",

        0x25: "CWLS1",
        0x65: "CWLS2",
        0x66: "CWLS3",
        0x67: "CWLS4",
        0x68: "CWLS5",
        0x69: "CWLS6",
        0x6A: "CWLS7",
        0x6B: "CWLS8",
    }

    def __init__(self):
        # Precompiled regex for normalizing whitespace
        self.space_re = re.compile(r"\s+")

    def parse_log_file(self, filename):
        """
        Parse the given binary log file and extract all records.
        Returns a list of parsed record dictionaries.
        """
        results = []

        with open(filename, "rb") as f:
            with mmap.mmap(f.fileno(), 0, access=mmap.ACCESS_READ) as data:
                # Read offset table and determine payload start
                offsets, payload_start = self._read_offset_table(data)
                payload_size = len(data) - payload_start

                # Filter and sort usable offsets
                usable_offsets = [o for o in offsets if 0 <= o < payload_size]
                usable_offsets = sorted(set(usable_offsets))

                # Parse each record using offsets
                for i, offset in enumerate(usable_offsets[:-1]):
                    start = payload_start + offset
                    end   = payload_start + usable_offsets[i + 1]
                    record = self._parse_record(data[start:end].rstrip(b"\x00"))
                    if record:
                        results.append(record)

                # Parse remaining blob for any additional records
                if usable_offsets:
                    blob = bytes(data[payload_start + usable_offsets[-1]:])
                    for r in self._scan_blob_for_records(blob):
                        results.append(r)

        return results

    def _scan_blob_for_records(self, blob: bytes) -> list:
        """
        Scan a binary blob for valid records by searching for plausible timestamps and record markers.
        Returns a list of parsed record dictionaries.
        """
        length = len(blob)
        starts = []
        for i in range(length - 8):
            ts_val = struct.unpack_from("<I", blob, i)[0]
            if not (1_370_000_000 <= ts_val <= 2_100_000_000):
                continue
            if blob[i + 8] == 0x1F:
                starts.append(i)

        results = []
        for j, start in enumerate(starts):
            end = starts[j + 1] if j + 1 < len(starts) else length
            record = self._parse_record(blob[start:end].rstrip(b"\x00"))
            if record:
                results.append(record)
        return results

    def _read_offset_table(self, data):
        """
        Read the offset table at the start of the log file.
        Returns a tuple of (offsets list, payload start position).
        """
        start_idx = struct.unpack_from("<I", data, 0)[0]
        end_idx   = struct.unpack_from("<I", data, 4)[0]
        record_count = end_idx - start_idx
        payload_start = 8 + record_count * 4
        offsets = list(struct.unpack_from(f"<{record_count}I", data, 8))

        return offsets, payload_start

    def _parse_record(self, record_data):
        """
        Parse a single record from binary data.
        Returns a dictionary with timestamp, channel, code, player, and message, or None if invalid.
        """

        if len(record_data) < 8:
            return None

        timestamp_val = struct.unpack_from("<I", record_data, 0)[0]

        if not (1370000000 <= timestamp_val <= 2100000000):
            return None

        code_val = struct.unpack_from("<I", record_data, 4)[0]

        timestamp = dt.datetime.fromtimestamp(timestamp_val).strftime(
            "%Y-%m-%d %H:%M:%S"
        )

        channel = code_val & 0xFF
        code_hex = f"{channel:04X}"

        payload = record_data[8:]

        fields = self._extract_fields(payload)

        if not fields:
            return None

        player = ""
        message = ""

        if len(fields) == 1:
            message = fields[0]
        else:
            player = fields[0]
            message = " ".join(fields[1:])

        if not message:
            return None

        channel_name = self.CHAT_TYPES.get(channel, f"Unknown({code_hex})")

        return {
            "timestamp": timestamp,
            "channel": channel_name,
            "code": code_hex,
            "player": player,
            "message": message,
        }

    def _tag_end(self, data: bytes, pos: int) -> int:
        """
        Find the end position of a tag in the binary data, starting from pos.
        Used for skipping over special tag sequences.
        """
        length = len(data)
        if pos + 3 > length:
            return length
        len_marker = data[pos + 2]
        if 0x01 <= len_marker < 0xF0:
            data_len = len_marker - 1
            end_of_tag = pos + 3 + data_len
            if end_of_tag < length and data[end_of_tag] == 0x03:
                return end_of_tag + 1
        end = data.find(b"\x03", pos + 1)
        return end + 1 if end != -1 else length

    def _extract_fields(self, payload: bytes) -> list:
        """
        Extract text fields from the payload by splitting on field separators and decoding.
        Returns a list of decoded strings.
        """
        chunks = []
        chunk_start = 0
        i = 0
        length = len(payload)

        while i < length:
            c = payload[i]
            if c == 0x02:
                i = self._tag_end(payload, i)
                continue
            if c == 0x1F:
                chunks.append(payload[chunk_start:i])
                chunk_start = i + 1
            i += 1

        if chunk_start < length:
            chunks.append(payload[chunk_start:])

        parts = []
        for chunk in chunks:
            text = self._decode_se_string(chunk)
            if text:
                parts.append(text)

        return parts

    def _decode_se_string(self, chunk):
        """
        Decode a single SE string chunk, removing tags and non-printable characters.
        Returns a cleaned string.
        """

        visible = bytearray()
        i = 0

        while i < len(chunk):

            c = chunk[i]

            if c == 0x02:

                if i + 2 >= len(chunk):
                    break

                tag_len = chunk[i + 2]

                i += 3 + tag_len
                continue

            if c in (0x00, 0x01, 0x03):
                i += 1
                continue

            visible.append(c)
            i += 1

        text = bytes(visible).decode("utf-8", errors="ignore")

        text = text.replace("\x00", "")
        text = "".join(c for c in text if c.isprintable() or c.isspace())

        text = self.space_re.sub(" ", text)

        text = text.replace(" : ", ":")
        text = text.replace(" , ", ",")

        return text.strip()

    def format_output(self, records):
        """
        Format parsed records into human-readable log lines.
        Returns a list of formatted strings.
        """

        lines = []

        for r in records:

            lines.append(
                f"[{r['timestamp']}] [{r['channel']}] "
                f"[{r['player']}] {r['message']}"
            )

        return lines


def output_path(input_file):
    """
    Generate output file path based on input log file name.
    """

    base = os.path.splitext(os.path.basename(input_file))[0]
    return os.path.join(os.path.dirname(input_file), base + "_parsed.txt")


def main():
    """
    Main entry point: parse command-line argument, process log file, and write output.
    """

    if len(sys.argv) != 2:
        print("Usage: python ffxiv_log_parser.py logfile")
        return

    log_file = sys.argv[1]

    parser = FFXIVLogParser()

    print("Parsing:", log_file)

    records = parser.parse_log_file(log_file)

    lines = parser.format_output(records)

    out = output_path(log_file)

    with open(out, "w", encoding="utf-8") as f:
        for line in lines:
            f.write(line + "\n")

    print("Completed:", len(lines), "records")
    print("Output:", out)

    print("\nPreview:")
    for line in lines[:10]:
        print(line)



# Run main if executed as a script
if __name__ == "__main__":
    main()
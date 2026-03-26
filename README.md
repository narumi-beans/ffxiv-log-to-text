# ffxiv-log-to-text
---

This repository contains parsers (Python and PHP versions) that analyze Final Fantasy XIV (FFXIV) log files and convert chat and battle records into a human-readable format.

### Demo

A live example using this parser is available here:
[https://m-ts.icu/](https://m-ts.icu/)

It parses FFXIV binary logs and displays chat and battle logs in a readable format.

### Features

* Parse FFXIV binary log files
* Extract chat, battle, and system logs
* Convert logs into a readable text format

### File Structure

* `ffxiv_l2t.py` — Python parser
* `ffxiv_l2t.php` — PHP parser

### Usage

Python:

```bash
python ffxiv_l2t.py <log_file>
```

Example:

```bash
python ffxiv_l2t.py sample.log
```

PHP:

```bash
php ffxiv_l2t.php <log_file>
```

Example:

```bash
php ffxiv_l2t.php sample.log
```

### Notes

* This tool is designed for FFXIV binary logs only
* Other formats may not be parsed correctly
* Output is encoded in UTF-8

### License

Released under the MIT License.

---

このリポジトリには、ファイナルファンタジーXIV（FFXIV）のログファイルを解析し、チャットやバトルの記録を人間が読みやすい形式に変換するパーサー（Python版・PHP版）が含まれています。

### デモ

実際にこのパーサーを使用したサイトはこちら：
[https://m-ts.icu/](https://m-ts.icu/)

FFXIVのバイナリログを解析し、チャットや戦闘ログを読みやすい形式で表示しています。

### 主な機能

* FFXIVバイナリログの解析
* チャット・戦闘・システムログの抽出
* 読みやすいテキスト形式へ変換

### ファイル構成

* `ffxiv_l2t.py` ・・・ Pythonによるログパーサー
* `ffxiv_l2t.php` ・・・ PHPによるログパーサー

### 使い方

Python:

```bash
python ffxiv_l2t.py <ログファイル名>
```

例:

```bash
python ffxiv_l2t.py sample.log
```

PHP:

```bash
php ffxiv_l2t.php <ログファイル名>
```

例:

```bash
php ffxiv_l2t.php sample.log
```

### 注意事項

* 本ツールはFFXIVのバイナリログ専用です
* 異なる形式のファイルでは正常に解析できない可能性があります
* 出力はUTF-8形式です

### ライセンス

MITライセンスで公開されています。

---

该仓库包含用于解析《最终幻想XIV》（FFXIV）日志文件的解析器（Python版和PHP版），可以将聊天和战斗记录转换为易于阅读的格式。

### 演示

使用该解析器的示例网站：
[https://m-ts.icu/](https://m-ts.icu/)

该网站解析FFXIV二进制日志，并以可读形式显示聊天和战斗记录。

### 功能

* 解析FFXIV二进制日志文件
* 提取聊天、战斗和系统日志
* 转换为易读的文本格式

### 文件结构

* `ffxiv_l2t.py` — Python解析器
* `ffxiv_l2t.php` — PHP解析器

### 使用方法

Python:

```bash
python ffxiv_l2t.py <日志文件>
```

示例:

```bash
python ffxiv_l2t.py sample.log
```

PHP:

```bash
php ffxiv_l2t.php <日志文件>
```

示例:

```bash
php ffxiv_l2t.php sample.log
```

### 注意事项

* 本工具仅适用于FFXIV二进制日志
* 其他格式可能无法正确解析
* 输出为UTF-8编码

### 许可证

基于MIT许可证发布。

---

이 저장소에는 파이널 판타지 XIV(FFXIV) 로그 파일을 분석하여 채팅 및 전투 기록을 사람이 읽기 쉬운 형식으로 변환하는 파서(Python 및 PHP 버전)가 포함되어 있습니다.

### 데모

이 파서를 사용한 예제 사이트:
[https://m-ts.icu/](https://m-ts.icu/)

FFXIV 바이너리 로그를 분석하여 채팅 및 전투 로그를 읽기 쉬운 형식으로 표시합니다.

### 주요 기능

* FFXIV 바이너리 로그 파일 분석
* 채팅, 전투 및 시스템 로그 추출
* 읽기 쉬운 텍스트 형식으로 변환

### 파일 구성

* `ffxiv_l2t.py` — Python 파서
* `ffxiv_l2t.php` — PHP 파서

### 사용 방법

Python:

```bash
python ffxiv_l2t.py <로그 파일>
```

예시:

```bash
python ffxiv_l2t.py sample.log
```

PHP:

```bash
php ffxiv_l2t.php <로그 파일>
```

예시:

```bash
php ffxiv_l2t.php sample.log
```

### 주의사항

* 본 도구는 FFXIV 바이너리 로그 전용입니다
* 다른 형식은 정상적으로 분석되지 않을 수 있습니다
* 출력은 UTF-8 형식입니다

### 라이선스

MIT 라이선스로 배포됩니다。

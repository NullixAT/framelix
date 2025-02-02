<?php

namespace Framelix\Framelix\Utils;

use Exception;
use Framelix\Framelix\Network\Response;
use PhpOffice\PhpSpreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Throwable;

use function header;
use function is_float;
use function is_int;
use function reset;
use function str_starts_with;

class SpreadsheetWrapper
{

    /**
     * @var Spreadsheet|null
     */
    public ?Spreadsheet $spreadsheet = null;

    /**
     * Create a caritas excel instance from file
     * @param string $path
     * @param bool $readOnlyMode
     * @return self
     * @throws PhpSpreadsheet\Reader\Exception
     */
    public static function createInstanceFromFile(string $path, bool $readOnlyMode = false): SpreadsheetWrapper
    {
        $spreadsheet = self::readFile($path, $readOnlyMode);
        return new self($spreadsheet);
    }

    /**
     * Read file and return spreadsheet
     * @param string $path
     * @param bool $readOnlyMode
     * @return Spreadsheet
     * @throws PhpSpreadsheet\Reader\Exception
     */
    public static function readFile(string $path, bool $readOnlyMode = false): Spreadsheet
    {
        $reader = IOFactory::createReaderForFile($path);
        if ($readOnlyMode) {
            $reader->setReadDataOnly(true);
        }
        return $reader->load($path);
    }

    /**
     * Create a new instance with a new spreadsheet
     * @return self
     */
    public static function create(): self
    {
        return new self(new Spreadsheet());
    }

    /**
     * Excel constructor.
     *
     * @param Spreadsheet|null $spreadsheet Pass a spreadsheet instance if required, otherwise it's a new instance
     */
    public function __construct(?Spreadsheet $spreadsheet = null)
    {
        $this->spreadsheet = $spreadsheet ?? new Spreadsheet();
    }

    /**
     * Convert given sheet to array
     * @param int|PhpSpreadsheet\Worksheet\Worksheet $sheet Could be sheet index or sheet instance
     * @param int|null $rowAsColumnIndexNames If int than use the cell names as array index
     * @return array
     */
    public function toArray(PhpSpreadsheet\Worksheet\Worksheet|int $sheet, ?int $rowAsColumnIndexNames = null): array
    {
        if (is_int($sheet)) {
            $sheet = $this->spreadsheet->getSheet($sheet);
        }
        $array = $sheet->toArray();
        if (is_int($rowAsColumnIndexNames)) {
            $newArray = [];
            $indexes = [];
            foreach ($array[$rowAsColumnIndexNames] as $key => $value) {
                $indexes[$key] = trim($value);
            }
            foreach ($array as $row) {
                $tmp = [];
                foreach ($row as $key => $value) {
                    $tmp[$indexes[$key]] = $value;
                }
                $newArray[] = $tmp;
            }
            $array = $newArray;
        }
        return $array;
    }

    /**
     * Set multiple sheet values from multidimensional array
     * @param array[][][] $array
     * @param bool $autoFormat Apply automatic format for specific data types like date, numbers, strings, objects
     * @param string|null $autoFilterRange Apply autofilter to the given cell range. eg: A1:*1 (asterisk = max column
     *     size of array)
     * @return PhpSpreadsheet\Worksheet\Worksheet[]
     */
    public function setFromArrayMultiple(
        array $array,
        bool $autoFormat = true,
        ?string $autoFilterRange = null
    ): array {
        $sheets = [];
        $sheet = null;
        foreach ($array as $sheetName => $sheetArray) {
            if ($sheet === null) {
                $sheet = $this->spreadsheet->getActiveSheet();
            } else {
                $sheet = $this->spreadsheet->createSheet();
            }
            $sheet->setTitle(mb_substr($sheetName, 0, 31));
            $this->setFromArray($sheetArray, $sheet, $autoFormat, $autoFilterRange);
            $sheets[$sheetName] = $sheet;
        }

        return $sheets;
    }

    /**
     * Set sheet values from array
     * @param array[][] $array
     * @param PhpSpreadsheet\Worksheet\Worksheet|null $sheet
     * @param bool $autoFormat Apply automatic format for specific data types like date, numbers, strings, objects
     * @param string|null $autoFilterRange Apply autofilter to the given cell range. eg: A1:*1 (asterisk = max column
     *     size of array)
     * @return PhpSpreadsheet\Worksheet\Worksheet
     */
    public function setFromArray(
        array $array,
        ?PhpSpreadsheet\Worksheet\Worksheet $sheet = null,
        bool $autoFormat = true,
        ?string $autoFilterRange = null
    ): PhpSpreadsheet\Worksheet\Worksheet {
        $sheet = $sheet ?? $this->spreadsheet->getActiveSheet();
        $sheet->getPageSetup()->setPaperSize(PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
        $sheet->getPageSetup()->clearPrintArea();
        $columnCount = 0;
        $firstRow = reset($array);
        $rowNr = 0;
        foreach ($array as $row) {
            $rowNr++;
            $cellNr = 1;
            foreach ($firstRow as $key => $ignored) {
                /** @var mixed $value */
                $value = $row[$key] ?? null;
                $cell = $sheet->getCell([$cellNr, $rowNr]);
                if ($autoFormat) {
                    if (is_array($value) || is_object($value) || is_bool($value)) {
                        $value = StringUtils::stringify($value);
                    }
                    if (is_string($value)) {
                        if (str_starts_with($value, "=")) {
                            $cell->setValueExplicit($value, PhpSpreadsheet\Cell\DataType::TYPE_FORMULA);
                        } else {
                            $cell = $cell->setValueExplicit($value);
                            $cell->getStyle()->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
                            $cell->getStyle()->setQuotePrefix(true);
                        }
                    } elseif (is_float($value) || is_int($value)) {
                        $cell->setValueExplicit($value, PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                    } elseif ($value === null) {
                        $cell->setValueExplicit($value, PhpSpreadsheet\Cell\DataType::TYPE_NULL);
                    } else {
                        $cell->setValue($value);
                    }
                } else {
                    $cell->setValue($value);
                }
                $cellNr++;
                if ($cellNr > $columnCount) {
                    $columnCount = $cellNr;
                }
            }
        }
        if ($autoFilterRange) {
            $autoFilterRange = mb_strtoupper($autoFilterRange);
            $split = explode(
                ":",
                str_replace(
                    "*",
                    PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnCount - 1),
                    $autoFilterRange
                )
            );
            $start = PhpSpreadsheet\Cell\Coordinate::coordinateFromString($split[0]);
            $end = PhpSpreadsheet\Cell\Coordinate::coordinateFromString($split[1]);
            $sheet->setAutoFilter([
                PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($start[0]),
                (int)$start[1],
                PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($end[0]),
                (int)$end[1],
            ]);
        }
        return $sheet;
    }

    /**
     * Save excel to given path
     * @param string $path
     */
    public function save(string $path): void
    {
        $writer = $this->getWriterByFilename($path);
        $writer->save($path);
    }

    /**
     * Download excel file
     * @param string $filename
     * @return never
     */
    public function download(string $filename): never
    {
        $writer = $this->getWriterByFilename($filename);
        Response::download(function () use ($writer) {
            try {
                $writer->save("php://output");
            } catch (Throwable) {
                header("Content-Type: text/plain");
                header("Content-Transfer-Encoding: 8bit");
            }
        }, $filename);
    }

    /**
     * Get writer
     * @param string $filename
     * @return PhpSpreadsheet\Writer\BaseWriter
     */
    private function getWriterByFilename(string $filename): PhpSpreadsheet\Writer\BaseWriter
    {
        $extension = strtolower(substr($filename, strrpos($filename, ".") + 1));
        if ($extension === "xls") {
            return new PhpSpreadsheet\Writer\Xls($this->spreadsheet);
        }
        if ($extension === "xlsx") {
            return new PhpSpreadsheet\Writer\Xlsx($this->spreadsheet);
        }
        if ($extension === "csv") {
            $writer = new PhpSpreadsheet\Writer\Csv($this->spreadsheet);
            $writer->setDelimiter(';');
            $writer->setEnclosure();
            $writer->setLineEnding("\r\n");
            return $writer;
        }
        throw new Exception("Missing writer for $filename");
    }

}
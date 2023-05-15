<?php

/*
 * (c) Mantas Varatiejus <var.mantas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MVar\LogParser;

use MVar\LogParser\Exception\MatchException;
use MVar\LogParser\Exception\ParserException;

/**
 * This is the class that helps to iterate through log file.
 */
class LogIterator implements \Iterator
{
    /**
     * @var LineParserInterface
     */
    private $parser;

    /**
     * @var string
     */
    private $logFile;

    /**
     * @var resource
     */
    private $fileHandler;

    /**
     * @var string
     */
    private $currentLine;

    /**
     * @var bool
     */
    private $skipEmptyLines;

    /**
     * Constructor.
     *
     * @param string              $logFile
     * @param LineParserInterface $parser
     * @param bool                $skipEmptyLines
     */
    public function __construct($logFile, $parser, $skipEmptyLines = true)
    {
        $this->logFile = $logFile;
        $this->parser = $parser;
        $this->skipEmptyLines = $skipEmptyLines;
    }

    /**
     * Destructor.
     */
    public function __destruct()
    {
        @fclose($this->fileHandler);
    }

    /**
     * Returns file handler.
     *
     * @return resource
     * @throws ParserException
     */
    protected function getFileHandler()
    {
        if ($this->fileHandler === null) {
            $fileHandler = @fopen($this->logFile, 'r');

            if ($fileHandler === false) {
                throw new ParserException('Can not open log file.');
            }

            $this->fileHandler = $fileHandler;
        }

        return $this->fileHandler;
    }

    /**
     * Reads single line from file.
     *
     * @throws ParserException
     */
    protected function readLine()
    {
        $buffer = '';

        while ($buffer === '') {
            if (($buffer = fgets($this->getFileHandler())) === false) {
                $this->currentLine = null;

                return;
            }
            $buffer = trim($buffer, "\n\r\0");

            if (!$this->skipEmptyLines) {
                break;
            }
        }

        $this->currentLine = $buffer;
    }

    /**
     * Returns parsed current line.
     *
     * @return array|null
     */
    public function current()
    {
        if ($this->currentLine === null) {
            $this->readLine();
        }

        try {
            $data = $this->parser->parseLine($this->currentLine);
        } catch (MatchException $exception) {
            $data = null;
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function next()
    {
        $this->readLine();
    }

    /**
     * Returns current line
     *
     * @return string
     */
    public function key()
    {
        return $this->currentLine;
    }

    /**
     * {@inheritdoc}
     */
    public function valid()
    {
        return !feof($this->getFileHandler()) || $this->currentLine;
    }

    /**
     * {@inheritdoc}
     */
    public function rewind()
    {
        rewind($this->getFileHandler());
    }
}

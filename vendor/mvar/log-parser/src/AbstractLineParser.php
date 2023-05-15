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
 * Abstract line parser.
 */
abstract class AbstractLineParser implements LineParserInterface
{
    /**
     * {@inheritdoc}
     */
    public function parseLine($line)
    {
        if (!is_string($line)) {
            throw new ParserException('Parser argument must be a string.');
        }

        $match = @preg_match($this->getPattern(), $line, $matches);

        if ($match === false) {
            $error = error_get_last();
            throw new ParserException("Matcher failure. Please check if given pattern is valid. ({$error["message"]})");
        }

        if (!$match) {
            throw new MatchException('Given line does not match predefined pattern.');
        }

        return $this->prepareParsedData($matches);
    }

    /**
     * Prepare parsed data (matches) for end user.
     *
     * @param array $matches
     *
     * @return array
     */
    protected function prepareParsedData(array $matches)
    {
        // Remove indexed values
        $filtered = array_filter(array_keys($matches), 'is_string');
        $result = array_intersect_key($matches, array_flip($filtered));
        $result = array_filter($result);

        return $result;
    }

    /**
     * Returns pattern of log line.
     *
     * @return string
     */
    abstract protected function getPattern();
}

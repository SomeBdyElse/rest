<?php
/*
 *  Copyright notice
 *
 *  (c) 2014 Daniel Corn <info@cundd.net>, cundd
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 */

/**
 * @author COD
 * Created 09.01.14 11:03
 */


namespace Cundd\Rest\Command;


use Cundd\Rest\Domain\Model\Document;
use Cundd\Rest\Domain\Repository\DocumentRepository;
use TYPO3\CMS\Extbase\Mvc\Controller\CommandController;

\Tx_CunddComposer_Autoloader::register();

class RestCommandController extends CommandController {
	/**
	 * ASCII command escape
	 */
	const ESCAPE = "\033";

	/**
	 * ASCII style normal
	 */
	const NORMAL = "[0m";

	/**
	 * ASCII color green
	 */
	const GREEN = "[0;32m";

	/**
	 * ASCII color red
	 */
	const RED = "[0;31m";

	/**
	 * Document repository
	 *
	 * @var \Cundd\Rest\Domain\Repository\DocumentRepository
	 * @inject
	 */
	protected $documentRepository;

	/**
	 * List all Documents
	 *
	 *
	 * @param string  $database Name of the database to list
	 * @param boolean $v Display the Documents body
	 */
	public function showDocumentsCommand($database = '', $v = FALSE) {
		if ($database) {
			$documents = $this->documentRepository->findByDatabase($database);
		} else {
			$documents = $this->documentRepository->findAllIgnoreDatabase();
		}
		foreach ($documents as $document) {
			$this->showDocument($document, $v);
		}
	}

	/**
	 * Remove all Documents from the given database
	 *
	 * @param string $database Name of the database to remove
	 */
	public function removeDatabaseCommand($database) {
		$this->documentRepository->setDatabase($database);
		$count = $this->documentRepository->countAll();

		if ($count == 0) {
			$this->outputLine('Database "' . $database . '" is empty');
			return;
		}


		// Ask before deleting
		$prompt = 'Remove ' . $count . ' documents from database "' . $database . '" [yn]?';
		if (function_exists('readline')) {
			$choice = readline($prompt);
		} else {
			echo $prompt . ' ';
			$choice = stream_get_line(STDIN, 1024, PHP_EOL);
		}

		if ($choice === 'y') {
			$this->outputLine(
				static::ESCAPE . static::RED
				. 'Deleting ' . $count . ' documents'
				. static::ESCAPE . static::NORMAL
			);
			$this->documentRepository->removeAllFromDatabase($database);
		} else {
			$this->outputLine('Nothing deleted');
		}
	}

	/**
	 * Displays information about the given Document
	 *
	 * @param Document $document
	 * @param bool     $showBody
	 */
	public function showDocument(Document $document, $showBody = FALSE) {
		$this->outputLine(
			static::ESCAPE . static::GREEN
			. 'Database: ' . $document->_getDb() . ' '
			. 'ID: ' . ($document->getId() ? $document->getId() : '(Missing ID)') . ' '
			. static::ESCAPE . static::NORMAL
		);

		if ($showBody) {
			$this->outputLine(
				$this->formatJsonData($document->_getDataProtected(), TRUE) . PHP_EOL
			);
		}
	}

	/**
	 * Returns a formatted json-encoded version of the given data
	 *
	 * @param mixed $data The data to format
	 * @param bool $isJsonString Set this to TRUE if the given data already is a JSON string
	 * @return string
	 */
	public function formatJsonData($data, $isJsonString = FALSE) {
		if (defined('JSON_PRETTY_PRINT')) {
			if ($isJsonString) {
				$data = json_decode($data, TRUE);
			}
			return json_encode($data, JSON_PRETTY_PRINT);
		}
		if ($isJsonString) {
			$output = $data;
		} else {
			$output = json_encode($data);
		}

		$output = str_replace('\\/', '/', $output);
		$output = str_replace(',', ',' . PHP_EOL, $output);
		$output = str_replace('{', '{' . PHP_EOL, $output);
		$output = str_replace('}', PHP_EOL . '}', $output);
		$output = str_replace('[{', '[' . PHP_EOL . '{', $output);
		$output = str_replace('{{', '{' . PHP_EOL . '{', $output);
		$output = str_replace('}]', '}' . PHP_EOL . ']', $output);
		$output = str_replace('}}', '}' . PHP_EOL . '}', $output);
		$output = str_replace('[', '[' . PHP_EOL, $output);
		$output = str_replace(']', PHP_EOL . ']', $output);

		$output = rtrim($output);

		$indentedDepth = 0;
		$indentedOutput = '';
		$lines = explode(PHP_EOL, $output);
		/*
		 * Loop through each line
		 */
		foreach ($lines as $line) {
			$trimmedLine = trim($line);

			/*
			 * Decrease the depth immediately if the current line's first
			 * character is a closing bracket
			 */
			if (
				substr($trimmedLine, 0, 1) === '}'
				|| substr($trimmedLine, 0, 1) === ']'
			) {
				$indentedDepth--;
			}

			/*
			 * Make the single line pretty
			 */
			$prettyLine = $line;
			$prettyLine = str_replace('":"', '": "', $prettyLine);
			$prettyLine = str_replace('":{', '": {', $prettyLine);
			$prettyLine = str_replace('":[', '": [', $prettyLine);

			/*
			 * Add the output
			 */
			$indentedOutput .= ''
				. str_repeat("\t", $indentedDepth)
				. $prettyLine
				. PHP_EOL;

			/*
			 * Increase the depth for the next line if the current line contains
			 * an opening bracket
			 */
			if (
				strpos($trimmedLine, '{') !== FALSE
				|| strpos($trimmedLine, '[') !== FALSE
			) {
				$indentedDepth++;
			}

			/*
			 * Decrease the depth of the next line if the current line contains
			 * a closing bracket which is NOT the first character of the current
			 * line
			 */
			if (
				strpos($trimmedLine, '}') > 0
				|| strpos($trimmedLine, ']') > 0
			) {
				$indentedDepth--;
			}
		}

		return $indentedOutput;
	}
}

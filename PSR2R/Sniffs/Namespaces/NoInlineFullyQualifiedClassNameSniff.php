<?php

namespace PSR2R\Sniffs\Namespaces;

use PHP_CodeSniffer_File;
use PSR2R\Tools\AbstractSniff;

/**
 * All inline FQCN must be moved to use statements.
 */
class NoInlineFullyQualifiedClassNameSniff extends AbstractSniff {

	/**
	 * @var array
	 */
	protected $existingStatements;

	/**
	 * @var array
	 */
	protected $newStatements = [];

	/**
	 * @var array
	 */
	protected $allStatements;

	/**
	 * @inheritDoc
	 */
	public function register() {
		return [T_NEW, T_FUNCTION, T_DOUBLE_COLON, T_CLASS];
	}

	/**
	 * @inheritDoc
	 */
	public function process(\PHP_CodeSniffer_File $phpcsFile, $stackPtr) {
		$tokens = $phpcsFile->getTokens();

		// Skip non-namespaces files for now
		if (!$this->hasNamespace($phpcsFile)) {
			return;
		}

		$namespaceStatement = $this->getNamespaceInfo($phpcsFile);

		$this->loadStatements($phpcsFile);

		if ($tokens[$stackPtr]['code'] === T_CLASS) {
			$this->checkUseForClass($phpcsFile, $stackPtr);
		} elseif ($tokens[$stackPtr]['code'] === T_NEW) {
			$this->checkUseForNew($phpcsFile, $stackPtr);
		} elseif ($tokens[$stackPtr]['code'] === T_DOUBLE_COLON) {
			$this->checkUseForStatic($phpcsFile, $stackPtr);
		} else {
			$this->checkUseForSignature($phpcsFile, $stackPtr);
		}
	}

	/**
	 * Checks extends, implements.
	 *
	 * @param \PHP_CodeSniffer_File $phpcsFile
	 * @param int $stackPtr
	 * @return void
	 */
	protected function checkUseForClass(\PHP_CodeSniffer_File $phpcsFile, $stackPtr) {
		$tokens = $phpcsFile->getTokens();

		//TODO
	}

	/**
	 * @param \PHP_CodeSniffer_File $phpcsFile
	 * @param int $stackPtr
	 * @return void
	 */
	protected function checkUseForNew(\PHP_CodeSniffer_File $phpcsFile, $stackPtr) {
		$tokens = $phpcsFile->getTokens();

		$nextIndex = $phpcsFile->findNext(\PHP_CodeSniffer_Tokens::$emptyTokens, $stackPtr + 1, null, true);
		$lastIndex = null;
		$i = $nextIndex;
		$extractedUseStatement = '';
		$lastSeparatorIndex = null;
		while (true) {
			if (!$this->isGivenKind([T_NS_SEPARATOR, T_STRING], $tokens[$i])) {
				break;
			}
			$lastIndex = $i;
			$extractedUseStatement .= $tokens[$i]['content'];

			if ($this->isGivenKind([T_NS_SEPARATOR], $tokens[$i])) {
				$lastSeparatorIndex = $i;
			}
			++$i;
		}

		if ($lastIndex === null || $lastSeparatorIndex === null) {
			return;
		}

		$extractedUseStatement = ltrim($extractedUseStatement, '\\');

		$className = '';
		for ($i = $lastSeparatorIndex + 1; $i <= $lastIndex; ++$i) {
			$className .= $tokens[$i]['content'];
		}

		$error = 'Use statement ' . $extractedUseStatement . ' for ' . $className . ' should be in use block.';
		$fix = $phpcsFile->addFixableError($error, $stackPtr, 'New');
		if (!$fix) {
			return;
		}

		$phpcsFile->fixer->beginChangeset();

		$addedUseStatement = $this->addUseStatement($phpcsFile, $className, $extractedUseStatement);

		for ($i = $nextIndex; $i <= $lastSeparatorIndex; ++$i) {
			$phpcsFile->fixer->replaceToken($i, '');
		}

		if ($addedUseStatement['alias'] !== null) {
			$phpcsFile->fixer->replaceToken($lastSeparatorIndex + 1, $addedUseStatement['alias']);
			for ($i = $lastSeparatorIndex + 2; $i <= $lastIndex; ++$i) {
				$phpcsFile->fixer->replaceToken($i, '');
			}
		}

		if ($nextIndex === $stackPtr + 1) {
			$phpcsFile->fixer->replaceToken($stackPtr, $tokens[$stackPtr]['content'] . ' ');
		}

		$phpcsFile->fixer->endChangeset();
	}

	/**
	 * @param \PHP_CodeSniffer_File $phpcsFile
	 * @param int $stackPtr
	 * @return void
	 */
	protected function checkUseForStatic(\PHP_CodeSniffer_File $phpcsFile, $stackPtr) {
		$tokens = $phpcsFile->getTokens();

		$prevIndex = $phpcsFile->findPrevious(\PHP_CodeSniffer_Tokens::$emptyTokens, $stackPtr - 1, null, true);

		$lastIndex = null;
		$i = $prevIndex;
		$extractedUseStatement = '';
		$firstSeparatorIndex = null;
		while (true) {
			if (!$this->isGivenKind([T_NS_SEPARATOR, T_STRING], $tokens[$i])) {
				break;
			}
			$lastIndex = $i;
			$extractedUseStatement = $tokens[$i]['content'] . $extractedUseStatement;

			if ($firstSeparatorIndex === null && $this->isGivenKind([T_NS_SEPARATOR], $tokens[$i])) {
				$firstSeparatorIndex = $i;
			}
			--$i;
		}

		if ($lastIndex === null || $firstSeparatorIndex === null) {
			return;
		}

		$extractedUseStatement = ltrim($extractedUseStatement, '\\');

		$className = '';
		for ($i = $firstSeparatorIndex + 1; $i <= $prevIndex; ++$i) {
			$className .= $tokens[$i]['content'];
		}

		$error = 'Use statement ' . $extractedUseStatement . ' for ' . $className . ' should be in use block.';
		$fix = $phpcsFile->addFixableError($error, $stackPtr, 'Static');
		if (!$fix) {
			return;
		}

		$phpcsFile->fixer->beginChangeset();

		$addedUseStatement = $this->addUseStatement($phpcsFile, $className, $extractedUseStatement);

		for ($i = $lastIndex; $i <= $firstSeparatorIndex; ++$i) {
			$phpcsFile->fixer->replaceToken($i, '');
		}

		if ($addedUseStatement['alias'] !== null) {
			$phpcsFile->fixer->replaceToken($firstSeparatorIndex + 1, $addedUseStatement['alias']);
			for ($i = $firstSeparatorIndex + 2; $i <= $lastIndex; ++$i) {
				$phpcsFile->fixer->replaceToken($i, '');
			}
		}

		$phpcsFile->fixer->endChangeset();
	}

	/**
	 * @param \PHP_CodeSniffer_File $phpcsFile
	 * @param int $stackPtr
	 * @return void
	 */
	protected function checkUseForSignature(\PHP_CodeSniffer_File $phpcsFile, $stackPtr) {
		$tokens = $phpcsFile->getTokens();

		$openParenthesisIndex = $phpcsFile->findNext(T_OPEN_PARENTHESIS, $stackPtr + 1);
		$closeParenthesisIndex = $tokens[$openParenthesisIndex]['parenthesis_closer'];

		for ($i = $openParenthesisIndex + 1; $i < $closeParenthesisIndex; $i++) {
			$lastIndex = null;
			$j = $i;
			$extractedUseStatement = '';
			$lastSeparatorIndex = null;
			while (true) {
				if (!$this->isGivenKind([T_NS_SEPARATOR, T_STRING], $tokens[$j])) {
					break;
				}

				$lastIndex = $j;
				$extractedUseStatement .= $tokens[$j]['content'];
				if ($this->isGivenKind([T_NS_SEPARATOR], $tokens[$j])) {
					$lastSeparatorIndex = $j;
				}
				++$j;
			}
			$i = $j;

			if ($lastIndex === null || $lastSeparatorIndex === null) {
				continue;
			}

			$extractedUseStatement = ltrim($extractedUseStatement, '\\');

			$className = '';
			for ($k = $lastSeparatorIndex + 1; $k <= $lastIndex; ++$k) {
				$className .= $tokens[$k]['content'];
			}

			$error = 'Use statement ' . $extractedUseStatement . ' for ' . $className . ' should be in use block.';
			$fix = $phpcsFile->addFixableError($error, $stackPtr, 'Signature');
			if (!$fix) {
				return;
			}

			//TODO
		}
	}

	/**
	 * @param \PHP_CodeSniffer_File $phpcsFile All the tokens found in the document.
	 *
	 * @return void
	 */
	protected function loadStatements(\PHP_CodeSniffer_File $phpcsFile) {
		if ($this->existingStatements !== null) {
			return;
		}

		$existingStatements = $this->getUseStatements($phpcsFile);
		$this->existingStatements = $existingStatements;
		$this->allStatements = $existingStatements;
		$this->newStatements = [];
	}

	/**
	 * @param \PHP_CodeSniffer_File $phpcsFile
	 *
	 * @return bool
	 */
	protected function isBlacklistedFile(\PHP_CodeSniffer_File $phpcsFile) {
		$file = $phpcsFile->getFilename();
		if (strpos($file, DIRECTORY_SEPARATOR . 'Fixtures' . DIRECTORY_SEPARATOR) !== false) {
			return true;
		}

		return false;
	}

	/**
	 * Another sniff takes care of that, we just ignore then.
	 *
	 * @param string $statementContent
	 *
	 * @return bool
	 */
	protected function isMultipleUseStatement($statementContent) {
		if (strpos($statementContent, ',') !== false) {
			return true;
		}

		return false;
	}

	/**
	 * @param string $shortName
	 * @param string $fullName
	 *
	 * @return string|null
	 */
	protected function generateUniqueAlias($shortName, $fullName) {
		$alias = $shortName;
		$count = 0;
		$pieces = explode('\\', $fullName);
		$pieces = array_reverse($pieces);
		array_shift($pieces);

		while (isset($this->allStatements[$alias])) {
			$alias = $shortName;

			if (count($pieces) - 1 < $count) {
				return null;
			}

			$prefix = '';
			for ($i = 0; $i <= $count; ++$i) {
				$prefix .= $pieces[$i];
			}

			$alias = $prefix . $alias;

			$count++;
		}

		return $alias;
	}

	/**
	 * @return array
	 */
	protected function getUseStatements(\PHP_CodeSniffer_File $phpcsFile) {
		$tokens = $phpcsFile->getTokens();

		$statements = [];
		foreach ($tokens as $index => $token) {
			if ($token['code'] !== T_USE) {
				continue;
			}

			$useStatementStartIndex = $phpcsFile->findNext(\PHP_CodeSniffer_Tokens::$emptyTokens, $index + 1, null, true);

			// Ignore function () use ($foo) {}
			if ($tokens[$useStatementStartIndex]['content'] === '(') {
				continue;
			}

			$semicolonIndex = $phpcsFile->findNext(T_SEMICOLON, $useStatementStartIndex + 1);
			$useStatementEndIndex = $phpcsFile->findPrevious(\PHP_CodeSniffer_Tokens::$emptyTokens, $semicolonIndex - 1, null, true);

			$statement = '';
			for ($i = $useStatementStartIndex; $i <= $useStatementEndIndex; $i++) {
				$statement .= $tokens[$i]['content'];
			}

			if ($this->isMultipleUseStatement($statement)) {
				continue;
			}

			$statementParts = preg_split('/\s+as\s+/i', $statement);

			if (count($statementParts) === 1) {
				$fullName = $statement;
				$statementParts = explode('\\', $fullName);
				$shortName = end($statementParts);
				$alias = null;
			} else {
				$fullName = $statementParts[0];
				$alias = $statementParts[1];
				$statementParts = explode('\\', $fullName);
				$shortName = end($statementParts);
			}

			$shortName = trim($shortName);
			$fullName = trim($fullName);
			$key = $alias ?: $shortName;

			$statements[$key] = [
				'alias' => $alias,
				'end' => $semicolonIndex,
				'fullName' => ltrim($fullName, '\\'),
				'shortName' => $shortName,
				'start' => $index,
			];
		}

		return $statements;
	}

	/**
	 * @param \PHP_CodeSniffer_File $phpcsFile
	 * @param string $shortName
	 * @param string $fullName
	 *
	 * @return array
	 */
	protected function addUseStatement(\PHP_CodeSniffer_File $phpcsFile, $shortName, $fullName) {
		foreach ($this->allStatements as $useStatement) {
			if ($useStatement['fullName'] === $fullName) {
				return $useStatement;
			}
		}

		$alias = $this->generateUniqueAlias($shortName, $fullName);
		if (!$alias) {
			throw new \RuntimeException('Could not generate unique alias.');
		}

		$result = [
			'alias' => $alias === $shortName ? null : $alias,
			'fullName' => $fullName,
			'shortName' => $shortName,
		];
		$this->insertUseStatement($phpcsFile, $result);

		$this->allStatements[$alias] = $result;
		$this->newStatements[$alias] = $result;

		return $result;
	}

	/**
	 * @param \PHP_CodeSniffer_File $phpcsFile
	 * @param array $useStatement
	 * @return void
	 */
	protected function insertUseStatement(PHP_CodeSniffer_File $phpcsFile, array $useStatement) {
		$tokens = $phpcsFile->getTokens();

		$existingStatements = $this->existingStatements;
		if ($existingStatements) {
			$lastOne = array_pop($existingStatements);

			$lastUseStatementIndex = $lastOne['end'];
		} else {
			$namespaceStatement = $this->getNamespaceInfo($phpcsFile);

			$lastUseStatementIndex = $namespaceStatement['end'];
		}

		$phpcsFile->fixer->addNewline($lastUseStatementIndex);
		$phpcsFile->fixer->addContent($lastUseStatementIndex, $this->generateUseStatement($useStatement));
	}

	/**
	 * @param array $useStatement
	 *
	 * @return string
	 */
	protected function generateUseStatement(array $useStatement) {
		$alias = '';
		if (!empty($useStatement['alias'])) {
			$alias = ' as ' . $useStatement['alias'];
		}

		$content = 'use ' . $useStatement['fullName'] . $alias . ';';

		return $content;
	}

}

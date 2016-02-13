<?php

namespace PSR2R\Sniffs\WhiteSpace;

use PHP_CodeSniffer_File;
use PHP_CodeSniffer_Sniff;

/**
 * Verifies that that object operator and class double colon have no additional whitespace around.
 */
class ObjectAttributeSpacingSniff implements PHP_CodeSniffer_Sniff {

/**
 * Returns an array of tokens this test wants to listen for.
 *
 * @return array
 */
	public function register() {
		return [T_OBJECT_OPERATOR, T_DOUBLE_COLON];
	}

/**
 * Processes this test, when one of its tokens is encountered.
 *
 * @param \PHP_CodeSniffer_File $phpcsFile All the tokens found in the document.
 * @param integer $stackPtr The position of the current token
 *    in the stack passed in $tokens.
 * @return void
 */
	public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr) {
		$tokens = $phpcsFile->getTokens();

		// Make sure there is no space before.
		$previousToken = $phpcsFile->findPrevious(T_WHITESPACE, ($stackPtr - 1), null, true);

		if ($stackPtr - $previousToken !== 1 && $tokens[$previousToken]['line'] === $tokens[$stackPtr]['line']) {
			$error = 'Expected no space before object operator';
			$phpcsFile->addFixableError($error, $stackPtr - 1, 'TooMany');
			if ($phpcsFile->fixer->enabled === true) {
				$phpcsFile->fixer->replaceToken($stackPtr - 1, '');
			}
		}

		// Make sure there is no space after.
		$nextToken = $phpcsFile->findNext(T_WHITESPACE, ($stackPtr + 1), null, true);

		if ($nextToken - $stackPtr !== 1 && $tokens[$nextToken]['line'] === $tokens[$stackPtr]['line']) {
			$error = 'Expected no space after object operator';
			$phpcsFile->addFixableError($error, $stackPtr + 1, 'TooMany');
			if ($phpcsFile->fixer->enabled === true) {
				$phpcsFile->fixer->replaceToken($stackPtr + 1, '');
			}
		}
	}

}

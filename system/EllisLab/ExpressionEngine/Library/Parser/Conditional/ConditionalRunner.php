<?php

namespace EllisLab\ExpressionEngine\Library\Parser\Conditional;

class ConditionalRunner {

	private $prefix = '';
	private $safety = FALSE;
	private $protect_javascript = TRUE;
	private $protected_javascript = array();

	/**
	 * Disable Javascript Protection
	 *
	 * By default all javascript is protected before the parser is run
	 * so that we don't end up mangling javascript that looks like it
	 * might be a conditional:
	 *
	 *  function() {if prompt('is this javascript?')} alert('yes');}
	 */
	public function disableProtectJavascript()
	{
		$this->protect_javascript = FALSE;
	}

	/**
	 * Turn safety on
	 *
	 * When the safety is on, all conditionals are evaluatable. This means
	 * that unknown / suspicious things are removed or turned to FALSE.
	 * Typically this is done in the last pass to make sure no conditionals
	 * are left in the template.
	 */
	public function safetyOn()
	{
		$this->safety = TRUE;
	}

	/**
	 * Set prefix
	 *
	 * Set a prefix to apply to all variables passed to the parser.
	 */
	public function setPrefix($prefix)
	{
		$this->prefix = $prefix;
	}

	/**
	 * Process conditionals
	 *
	 * @param	string $str		The template string containing conditionals
	 * @param	string $vars	The variables to look for in the conditionals
	 * @return	string The new template to use instead of $str.
	 */
	public function processConditionals($str, $vars)
	{
		$lexer = new ConditionalLexer();

		// Get the token stream
		$tokens = $lexer->tokenize(
			$this->protectJavascript($str)
		);

		$parser = new ConditionalParser($tokens);

		$parser->setVariables(
			$this->prefixVariables($vars)
		);

		if ($this->safety === TRUE)
		{
			$parser->safetyOn();
		}

		$output = $parser->parse();

		return $this->unProtectJavascript($output);
	}

	/**
	 * Apply our prefix to all variables
	 *
	 * @param Array $vars  All passed in variables
	 * @return Array       $vars but with the keys prefixed
	 */
	private function prefixVariables(array $vars)
	{
		$prefixed_vars = array();

		foreach ($vars as $key => $var)
		{
			$prefixed_vars[$this->prefix.$key] = $var;
		}

		return $prefixed_vars;
	}

	/**
	 * Protect compressed javascript.
	 *
	 * @see `$this->disableProtectJavascript()` for why we do this.
	 *
	 * @param String $str The raw template string
	 * @return String     The template string with javascript escaped
	 */
	private function protectJavascript($str)
	{
		if ($this->protect_javascript === FALSE)
		{
			return $str;
		}

		$js_protect = unique_marker('tmpl_script');

		if (stristr($str, '<script') && preg_match_all('/<script.*?>.*?<\/script>/is', $str, $matches))
		{
			foreach ($matches[0] as $i => $match)
			{
				$this->protected_javascript[$js_protect.$i] = $match;
			}

			$str = str_replace(
				array_values($this->protected_javascript),
				array_keys($this->protected_javascript),
				$str
			);
		}

		return $str;
	}

	/**
	 * Remove compressed javascript protection
	 *
	 * @see `$this->disableProtectJavascript()` for why we do this.
	 *
	 * @param String $str The parsed template string
	 * @return String     The template string with the javascript put back
	 */
	private function unProtectJavascript($str)
	{
		// Unprotect <script> tags
		if ($this->protect_javascript !== FALSE && count($this->protected_javascript) > 0)
		{
			$str = str_replace(
				array_keys($this->protected_javascript),
				array_values($this->protected_javascript),
				$str
			);
		}

		return $str;
	}

}
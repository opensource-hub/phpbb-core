<?php
/**
 *
 * This file is part of the phpBB Forum Software package.
 *
 * @copyright (c) phpBB Limited <https://www.phpbb.com>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 * For full copyright and license information, please see
 * the docs/CREDITS.txt file.
 *
 */

namespace phpbb\composer\io;

use Composer\IO\BufferIO;
use phpbb\language\language;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class web_io extends BufferIO
{
	use translate_composer_trait;

	/**
	 * Constructor.
	 *
	 * @param InputInterface	$input		The input instance
	 * @param OutputInterface	$output		The output instance
	 * @param HelperSet			$helperSet	The helperSet instance
	 */
	public function __construct(InputInterface $input, OutputInterface $output, HelperSet $helperSet, language $language)
	{
		$this->language = $language;

		parent::__construct($input, $output, $helperSet);
	}
}

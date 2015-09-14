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

namespace phpbb\composer;

use Composer\IO\IOInterface;
use phpbb\cache\driver\driver_interface;
use phpbb\composer\exception\managed_with_clean_error_exception;
use phpbb\composer\exception\managed_with_enable_error_exception;
use phpbb\composer\exception\managed_with_error_exception;
use phpbb\composer\exception\runtime_exception;
use phpbb\extension\manager as ext_manager;
use phpbb\filesystem\exception\filesystem_exception;
use phpbb\filesystem\filesystem;

/**
 * Class to safely manage extensions through composer.
 */
class extension_manager extends manager
{
	/**
	 * @var \phpbb\extension\manager
	 */
	protected $extension_manager;

	/**
	 * @var \phpbb\filesystem\filesystem
	 */
	protected $filesystem;

	/**
	 * @var array
	 */
	private $enabled_extensions;

	/**
	 * @param installer			$installer			Installer object
	 * @param driver_interface	$cache				Cache object
	 * @param ext_manager		$extension_manager	phpBB extension manager
	 * @param filesystem		$filesystem			Filesystem object
	 * @param string			$package_type		Composer type of managed packages
	 * @param string			$exception_prefix	Exception prefix to use
	 */
	public function __construct(installer $installer, driver_interface $cache, ext_manager $extension_manager, filesystem $filesystem, $package_type, $exception_prefix)
	{
		$this->extension_manager = $extension_manager;
		$this->filesystem = $filesystem;

		parent::__construct($installer, $cache, $package_type, $exception_prefix);
	}

	/**
	 * {@inheritdoc}
	 */
	public function pre_install(array $packages, IOInterface $io = null)
	{
		$installed_manually = array_intersect(array_keys($this->extension_manager->all_available()), array_keys($packages));
		if (count($installed_manually) !== 0)
		{
			throw new runtime_exception($this->exception_prefix, 'ALREADY_INSTALLED_MANUALLY', [implode('|', array_keys($installed_manually))]);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	protected function pre_update(array $packages, IOInterface $io = null)
	{
		$io->writeError('DISABLING_EXTENSIONS', true, 1);
		$this->enabled_extensions = [];
		foreach ($packages as $package)
		{
			try
			{
				if ($this->extension_manager->is_enabled($package))
				{
					$this->enabled_extensions[] = $package;
					$this->extension_manager->disable($package);
				}
			}
			catch (\phpbb\exception\runtime_exception $e)
			{
				$io->writeError([$e->getMessage(), $e->get_parameters()], true, 4);
			}
			catch (\Exception $e)
			{
				$io->writeError($e->getMessage(), true, 4);
			}
		}
	}

	/**
	 * {@inheritdoc}
	 */
	protected function post_update(array $packages, IOInterface $io = null)
	{
		$io->writeError('ENABLING_EXTENSIONS', true, 1);
		foreach ($this->enabled_extensions as $package)
		{
			try
			{
				$this->extension_manager->enable($package);
			}
			catch (\phpbb\exception\runtime_exception $e)
			{
				$io->writeError([$e->getMessage(), $e->get_parameters()], true, 4);
			}
			catch (\Exception $e)
			{
				$io->writeError($e->getMessage(), true, 4);
			}
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function remove(array $packages, IOInterface $io = null)
	{
		$packages = $this->normalize_version($packages);

		$not_installed = array_diff(array_keys($packages), array_keys($this->extension_manager->all_available()));
		if (count($not_installed) !== 0)
		{
			throw new runtime_exception($this->exception_prefix, 'NOT_INSTALLED', [implode('|', array_keys($not_installed))]);
		}

		parent::remove($packages, $io);
	}

	/**
	 * {@inheritdoc}
	 */
	public function pre_remove(array $packages, IOInterface $io = null)
	{
		$io->writeError('DISABLING_EXTENSIONS', true, 1);
		foreach ($packages as $package)
		{
			try
			{
				if ($this->extension_manager->is_enabled($package))
				{
					$this->extension_manager->disable($package);
				}
			}
			catch (\phpbb\exception\runtime_exception $e)
			{
				$io->writeError([$e->getMessage(), $e->get_parameters()], true, 4);
			}
			catch (\Exception $e)
			{
				$io->writeError($e->getMessage(), true, 4);
			}
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function start_managing($package, $io)
	{
		if (!$this->extension_manager->is_available($package))
		{
			throw new runtime_exception($this->exception_prefix, 'NOT_INSTALLED', [$package]);
		}

		if ($this->is_managed($package))
		{
			throw new runtime_exception($this->exception_prefix, 'ALREADY_MANAGED', [$package]);
		}

		$enabled = false;
		if ($this->extension_manager->is_enabled($package))
		{
			$enabled = true;
			$io->writeError('DISABLING_EXTENSION', true, 1);
			$this->extension_manager->disable($package);
		}

		$ext_path = $this->extension_manager->get_extension_path($package, true);
		$backup_path = rtrim($ext_path, '/') . '__backup__';

		try
		{
			$this->filesystem->rename($ext_path, $backup_path);
		}
		catch (filesystem_exception $e)
		{
			throw new runtime_exception($this->exception_prefix, 'CANNOT_MANAGE_FILESYSTEM_ERROR', [$package], $e);
		}

		try
		{
			$this->install((array) $package, $io);
			$this->filesystem->remove($backup_path);
		}
		catch (runtime_exception $e)
		{
			$this->filesystem->rename($backup_path, $ext_path);
			throw new runtime_exception($this->exception_prefix, 'CANNOT_MANAGE_INSTALL_ERROR', [$package], $e);
		}
		catch (filesystem_exception $e)
		{
			throw new managed_with_clean_error_exception($this->exception_prefix, 'MANAGED_WITH_CLEAN_ERROR', [$package, $backup_path], $e);
		}

		if ($enabled)
		{
			try
			{
				$io->writeError('ENABLING_EXTENSION', true, 1);
				$this->extension_manager->enabling($package);
			}
			catch (\Exception $e)
			{
				throw new managed_with_enable_error_exception($this->exception_prefix, 'MANAGED_WITH_ENABLE_ERROR', [$package], $e);
			}
		}
	}
}

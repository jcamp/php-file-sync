<?php

/*
 *  PHP File Synchronization Script
 *  Copyright (C) 2014 Sergio Bobillier Ceballos <sergio.bobillier@gmail.com>
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

require_once("file_synchronizer.php");
require_once("loggers/console_logger.php");

/*******************************************************************************
 * This is the main script
 *
 * Run this file to use the script from the command line. This file will load
 * the settings from the settings.php file into the class. The file will also
 * load the last syncrhonization date from disk and start the synchronization.
 *
 */

// Load the settings file and instantiate the class.

$settings = array();
require_once("settings.php");
$file_synchronizer = new File_Synchronizer($settings);

$logger = null;

// If debug_mode is enabled in the settings array we create a new console
// logger and pass it to the class so it logs it's output to it.

if($settings["debug_mode"] == true)
{
	$logger = new Console_Logger();
	$file_synchronizer->set_logger($logger);
}

/*******************************************************************************
 * Last synchronization date
 *
 * The program tries to load the date of the last synchronization from a file
 * called .last-sync in the current path. The field should contain the unix
 * timestamp of the last synchronization.
 *
 * If the file cannot be opened or the contents are not valid the script asumes
 * that the two paths have never been synchronized.
 *
 */

// Load and set up the time of the last synchronization

if($logger != null)
	$logger->log_message("Trying to retrieve the time of the last synchronization...");

$last_sync_time = 0;

$last_sync_file_name = ".last-sync";
$last_sync_file = @fopen($last_sync_file_name, "r");

if($last_sync_file)
{
	$last_sync_time = fread($last_sync_file, 20);
	$reg_ex = "/^[0-9]+$/";
	
	if(!preg_match($reg_ex, $last_sync_time))
	{
		if($logger != null)
		{
			$logger->log_message("Last synchronization time does not have the right format.");
			$logger->log_message("Asuming the paths have never been synchronized before.");
		}

		$last_sync_time = 0;
	}

	fclose($last_sync_file);
}
else
{
	if($logger != null)
		$logger->log_message("Could not open file asuming the paths have never been synchronized");
}

$file_synchronizer->set_last_sync_time($last_sync_time);

try
{
	if($logger != null)
		$logger->log_message("Starting synchronization...");

	// Start the synchronization

	$file_synchronizer->start_sync();

	// We save the current time to the file. This is the time the last
	// synchronization was made.

	if($logger != null)
		$logger->log_message("Saving the last synchronization time to disk...");

	$last_sync_time = time();

	if(!$file_synchronizer->get_simulate())
	{
		$last_sync_file = @fopen($last_sync_file_name, "w");
			
		if($last_sync_file)
		{
			fwrite($last_sync_file, time());
			fclose($last_sync_file);
		}
		else
		{
			if($logger != null)
				$logger->log_message("Could not save the last synchronization time, unable to open the file for writing.");
		}
	}

	// End of the script

	if($logger != null)
		$logger->log_message("Synchronization successful!");

	exit(0);
}
catch(Synchronization_Exception $sync_ex)
{
	if($logger != null)
	{
		$logger->log_message("ERROR: The synchronization process have failed!. The exit code is: " . $sync_ex->get_exit_code());
		$logger->log_message("\t" . $sync_ex->getMessage());
	}

	exit($sync_ex->get_exit_code());
}

?>

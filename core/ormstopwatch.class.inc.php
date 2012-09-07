<?php
// Copyright (C) 2010-2012 Combodo SARL
//
//   This program is free software; you can redistribute it and/or modify
//   it under the terms of the GNU General Public License as published by
//   the Free Software Foundation; version 3 of the License.
//
//   This program is distributed in the hope that it will be useful,
//   but WITHOUT ANY WARRANTY; without even the implied warranty of
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//   GNU General Public License for more details.
//
//   You should have received a copy of the GNU General Public License
//   along with this program; if not, write to the Free Software
//   Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

require_once('backgroundprocess.inc.php');

/**
 * ormStopWatch
 * encapsulate the behavior of a stop watch that will be stored as an attribute of class AttributeStopWatch 
 *
 * @author      Erwan Taloc <erwan.taloc@combodo.com>
 * @author      Romain Quetiez <romain.quetiez@combodo.com>
 * @author      Denis Flaven <denis.flaven@combodo.com>
 * @license     http://www.opensource.org/licenses/gpl-3.0.html LGPL
 */


/**
 * ormStopWatch
 * encapsulate the behavior of a stop watch that will be stored as an attribute of class AttributeStopWatch 
 *
 * @package     itopORM
 */
class ormStopWatch
{
	protected $iTimeSpent; // seconds
	protected $iStarted; // unix time (seconds)
	protected $iLastStart; // unix time (seconds)
	protected $iStopped; // unix time (seconds)
	protected $aThresholds;
	
	/**
	 * Constructor
	 */
	public function __construct($iTimeSpent = 0, $iStarted = null, $iLastStart = null, $iStopped = null)
	{
		$this->iTimeSpent = (int) $iTimeSpent;
		$this->iStarted = $iStarted;
		$this->iLastStart = $iLastStart;
		$this->iStopped = $iStopped;

		$this->aThresholds = array();
	}

	// BUGGY - DOES NOT DETECT A CHANGE IN THE DEADLINE
	//
	public function HasSameContents($oStopWatch)
	{
		if ($oStopWatch->iTimeSpent != $this->iTimeSpent) return false;
		if ($oStopWatch->iStarted != $this->iStarted) return false;
		if ($oStopWatch->iLastStart != $this->iLastStart) return false;
		if ($oStopWatch->iStopped != $this->iStopped) return false;
		if ($oStopWatch->aThresholds != $this->aThresholds) return false;

		// Array comparison is not recursive... let's do it by myself
		foreach ($oStopWatch->aThresholds as $iPercent => $aThresholdData)
		{
			// Assumption: the thresholds will not change dynamically (defined at application design time)
			$aThisThresholdData = $this->aThresholds[$iPercent];
			if ($aThisThresholdData['deadline'] != $aThresholdData['deadline']) return false;
			if ($aThisThresholdData['passed'] != $aThresholdData['passed']) return false;
			if ($aThisThresholdData['triggered'] != $aThresholdData['triggered']) return false;
			if ($aThisThresholdData['overrun'] != $aThresholdData['overrun']) return false;
		}

return false;

		return true;
	}

	/**
	 * Necessary for the triggers
	 */	 	
	public function __toString()
	{
		return (string) $this->iTimeSpent;
	}

	public function DefineThreshold($iPercent, $tDeadline = null, $bPassed = false, $bTriggered = false, $iOverrun = 0)
	{
		$this->aThresholds[$iPercent] = array(
			'deadline' => $tDeadline, // unix time (seconds)
			'passed' => $bPassed,
			'triggered' => $bTriggered,
			'overrun' => $iOverrun
		);
	}

	public function MarkThresholdAsTriggered($iPercent)
	{
		$this->aThresholds[$iPercent]['triggered'] = true;
	}

	public function GetTimeSpent()
	{
		return $this->iTimeSpent;
	}

	public function GetStartDate()
	{
		return $this->iStarted;
	}

	public function GetLastStartDate()
	{
		return $this->iLastStart;
	}

	public function GetStopDate()
	{
		return $this->iStopped;
	}

	public function GetThresholdDate($iPercent)
	{
		if (array_key_exists($iPercent, $this->aThresholds))
		{
			return $this->aThresholds[$iPercent]['deadline'];
		}
		else
		{
			return null;
		}
	}

	public function GetOverrun($iPercent)
	{
		if (array_key_exists($iPercent, $this->aThresholds))
		{
			return $this->aThresholds[$iPercent]['overrun'];
		}
		else
		{
			return null;
		}
	}
	public function IsThresholdPassed($iPercent)
	{
		if (array_key_exists($iPercent, $this->aThresholds))
		{
			return $this->aThresholds[$iPercent]['passed'];
		}
		else
		{
			return false;
		}
	}
	public function IsThresholdTriggered($iPercent)
	{
		if (array_key_exists($iPercent, $this->aThresholds))
		{
			return $this->aThresholds[$iPercent]['triggered'];
		}
		else
		{
			return false;
		}
	}

	public function GetAsHTML($oAttDef, $oHostObject = null)
	{
		$aProperties = array();

		$aProperties['States'] = implode(', ', $oAttDef->GetStates());

		if (is_null($this->iLastStart))
		{
			if (is_null($this->iStarted))
			{
				$aProperties['Elapsed'] = 'never started';
			}
			else
			{
				$aProperties['Elapsed'] = $this->iTimeSpent.' s';
			}
		}
		else
		{
			$iElapsedTemp = $this->ComputeDuration($oHostObject, $oAttDef, $this->iLastStart, time());
			$aProperties['Elapsed'] = $this->iTimeSpent.' + '.$iElapsedTemp.' s + <img src="../images/indicator.gif">';
		}

		$aProperties['Started'] = $oAttDef->SecondsToDate($this->iStarted);
		$aProperties['LastStart'] = $oAttDef->SecondsToDate($this->iLastStart);
		$aProperties['Stopped'] = $oAttDef->SecondsToDate($this->iStopped);

		foreach ($this->aThresholds as $iPercent => $aThresholdData)
		{
			$sThresholdDesc = $oAttDef->SecondsToDate($aThresholdData['deadline']);
			if ($aThresholdData['triggered'])
			{
				if ($aThresholdData['overrun'])
				{
					$sThresholdDesc .= " <b>TRIGGERED</b>, Overrun:".(int) $aThresholdData['overrun']." seconds";
				}
				else
				{
					// Still active, overrun unknown
					$sThresholdDesc .= " <b>TRIGGERED</b>";
				}
			}
			$aProperties[$iPercent.'%'] = $sThresholdDesc;
		}
		$sRes = "<TABLE class=\"listResults\">";
		$sRes .= "<TBODY>";
		foreach ($aProperties as $sProperty => $sValue)
		{
			$sRes .= "<TR>";
			$sCell = str_replace("\n", "<br>\n", $sValue);
			$sRes .= "<TD class=\"label\">$sProperty</TD><TD>$sCell</TD>";
			$sRes .= "</TR>";
		}
		$sRes .= "</TBODY>";
		$sRes .= "</TABLE>";
		return $sRes;
	}

	protected function ComputeGoal($oObject, $oAttDef)
	{
		$sMetricComputer = $oAttDef->Get('goal_computing');
		$oComputer = new $sMetricComputer();
		$aCallSpec = array($oComputer, 'ComputeMetric');
		if (!is_callable($aCallSpec))
		{
			throw new CoreException("Unknown class/verb '$sMetricComputer/ComputeMetric'");
		}
		$iRet = call_user_func($aCallSpec, $oObject);
		return $iRet;
	}

	protected function ComputeDeadline($oObject, $oAttDef, $iStartTime, $iDurationSec)
	{
		$sWorkingTimeComputer = $oAttDef->Get('working_time_computing');
		$aCallSpec = array($sWorkingTimeComputer, '__construct');
		if (!is_callable($aCallSpec))
		{
			//throw new CoreException("Pas de constructeur pour $sWorkingTimeComputer!");
		}
		$oComputer = new $sWorkingTimeComputer();
		$aCallSpec = array($oComputer, 'GetDeadline');
		if (!is_callable($aCallSpec))
		{
			throw new CoreException("Unknown class/verb '$sWorkingTimeComputer/GetDeadline'");
		}
		// GetDeadline($oObject, $iDuration, DateTime $oStartDate)
		$oStartDate = new DateTime('@'.$iStartTime); // setTimestamp not available in PHP 5.2
		$oDeadline = call_user_func($aCallSpec, $oObject, $iDurationSec, $oStartDate);
		$iRet = $oDeadline->format('U');
		return $iRet;
	}

	protected function ComputeDuration($oObject, $oAttDef, $iStartTime, $iEndTime)
	{
		$sWorkingTimeComputer = $oAttDef->Get('working_time_computing');
		$oComputer = new $sWorkingTimeComputer();
		$aCallSpec = array($oComputer, 'GetOpenDuration');
		if (!is_callable($aCallSpec))
		{
			throw new CoreException("Unknown class/verb '$sWorkingTimeComputer/GetOpenDuration'");
		}
		// GetOpenDuration($oObject, DateTime $oStartDate, DateTime $oEndDate)
		$oStartDate = new DateTime('@'.$iStartTime); // setTimestamp not available in PHP 5.2
		$oEndDate = new DateTime('@'.$iEndTime);
		$iRet = call_user_func($aCallSpec, $oObject, $oStartDate, $oEndDate);
		return $iRet;
	}

	public function Reset($oObject, $oAttDef)
	{
		$this->iTimeSpent = 0;
		$this->iStarted = null;
		$this->iLastStart = null;
		$this->iStopped = null;

		foreach ($this->aThresholds as $iPercent => &$aThresholdData)
		{
			$aThresholdData['passed'] = false;
			$aThresholdData['triggered'] = false;
			$aThresholdData['deadline'] = null;
			$aThresholdData['overrun'] = null;
		}
	}

	/**
	 * Start or continue
	 * It is the responsibility of the caller to compute the deadlines
	 * (to avoid computing twice for the same result) 	 
	 */	 	 	
	public function Start($oObject, $oAttDef)
	{
		if (!is_null($this->iLastStart))
		{
			// Already started
			return false;
		}

		if (is_null($this->iStarted))
		{
			$this->iStarted = time();
		}
		$this->iLastStart = time();
		$this->iStopped = null;

		return true;
	}

	/**
	 * Compute or recompute the goal and threshold deadlines
	 */	 	 	
	public function ComputeDeadlines($oObject, $oAttDef)
	{
		if (is_null($this->iLastStart))
		{
			// Currently stopped - do nothing
			return false;
		}
		
		$iDurationGoal = $this->ComputeGoal($oObject, $oAttDef);
		foreach ($this->aThresholds as $iPercent => &$aThresholdData)
		{
			if (is_null($iDurationGoal))
			{
				// No limit: leave null thresholds
				$aThresholdData['deadline'] = null;
			}
			else
			{
				$iThresholdDuration = round($iPercent * $iDurationGoal / 100);
				$aThresholdData['deadline'] = $this->ComputeDeadline($oObject, $oAttDef, $this->iLastStart, $iThresholdDuration - $this->iTimeSpent);
				// OR $aThresholdData['deadline'] = $this->ComputeDeadline($oObject, $oAttDef, $this->iStarted, $iThresholdDuration);

			}
			if (is_null($aThresholdData['deadline']) || ($aThresholdData['deadline'] > time()))
			{
				// The threshold is in the future, reset
				$aThresholdData['passed'] = false;
				$aThresholdData['triggered'] = false;
			}
			else
			{
				// The new threshold is in the past
				$aThresholdData['passed'] = true;
			}
		}

		return true;
	}

	/**
	 * Stop counting if not already done
	 */	 	 	
	public function Stop($oObject, $oAttDef)
	{
		if (is_null($this->iLastStart))
		{
			// Already stopped
			return false;
		}

		$iElapsed = $this->ComputeDuration($oObject, $oAttDef, $this->iLastStart, time());
		$this->iTimeSpent = $this->iTimeSpent + $iElapsed;

		foreach ($this->aThresholds as $iPercent => &$aThresholdData)
		{
			if (!is_null($aThresholdData['deadline']) && (time() > $aThresholdData['deadline']))
			{
				$aThresholdData['passed'] = true;
				if ($aThresholdData['overrun'] > 0)
				{
					// Accumulate from last start
					$aThresholdData['overrun'] += $iElapsed;
				}
				else
				{
					// First stop after the deadline has been passed
					$iOverrun = $this->ComputeDuration($oObject, $oAttDef, $aThresholdData['deadline'], time());
					$aThresholdData['overrun'] = $iOverrun;
				}
			}
		}

		$this->iLastStart = null;
		$this->iStopped = time();

		return true;
	}
}

/**
 * CheckStopWatchThresholds
 * Implements the automatic actions 
 *
 * @package     itopORM
 */
class CheckStopWatchThresholds implements iBackgroundProcess
{
	public function GetPeriodicity()
	{	
		return 10; // seconds
	}

	public function Process($iTimeLimit)
	{
		foreach (MetaModel::GetClasses() as $sClass)
		{
			foreach (MetaModel::ListAttributeDefs($sClass) as $sAttCode => $oAttDef)
			{
				if ($oAttDef instanceof AttributeStopWatch)
				{
					foreach ($oAttDef->ListThresholds() as $iThreshold => $aThresholdData)
					{
						$iPercent = $aThresholdData['percent']; // could be different than the index !
		
						$sExpression = "SELECT $sClass WHERE {$sAttCode}_laststart AND {$sAttCode}_{$iThreshold}_triggered = 0 AND {$sAttCode}_{$iThreshold}_deadline < NOW()";
						//echo $sExpression."<br/>\n";
						$oFilter = DBObjectSearch::FromOQL($sExpression);
						$aList = array();
						$oSet = new DBObjectSet($oFilter);
						while ((time() < $iTimeLimit) && ($oObj = $oSet->Fetch()))
						{
							$sClass = get_class($oObj);

							$aList[] = $sClass.'::'.$oObj->GetKey().' '.$sAttCode.' '.$iThreshold;
							//echo $sClass.'::'.$oObj->GetKey().' '.$sAttCode.' '.$iThreshold."\n";

							// Execute planned actions
							//
							foreach ($aThresholdData['actions'] as $aActionData)
							{
								$sVerb = $aActionData['verb'];
								$aParams = $aActionData['params'];
								$sParams = implode(', ', $aParams);
								//echo "Calling: $sVerb($sParams)<br/>\n";
								$aCallSpec = array($oObj, $sVerb);
								call_user_func_array($aCallSpec, $aParams);
							}

							// Mark the threshold as "triggered"
							//
							$oSW = $oObj->Get($sAttCode);
							$oSW->MarkThresholdAsTriggered($iThreshold);
							$oObj->Set($sAttCode, $oSW);
		
							if($oObj->IsModified())
							{
								// Todo - factorize so that only one single change will be instantiated
								$oMyChange = new CMDBChange();
								$oMyChange->Set("date", time());
								$oMyChange->Set("userinfo", "Automatic - threshold triggered");
								$iChangeId = $oMyChange->DBInsertNoReload();
		
								$oObj->DBUpdateTracked($oMyChange, true /*skip security*/);
							}

							// Activate any existing trigger
							// 
							$sClassList = implode("', '", MetaModel::EnumParentClasses($sClass, ENUM_PARENT_CLASSES_ALL));
							$oSet = new DBObjectSet(
								DBObjectSearch::FromOQL("SELECT TriggerOnThresholdReached AS t WHERE t.target_class IN ('$sClassList') AND stop_watch_code=:stop_watch_code AND threshold_index = :threshold_index"),
								array(), // order by
								array('stop_watch_code' => $sAttCode, 'threshold_index' => $iThreshold)
							);
							while ($oTrigger = $oSet->Fetch())
							{
								$oTrigger->DoActivate($oObj->ToArgs('this'));
							}
						}
					}
				}
			}
		}

		$iProcessed = count($aList);
		return "Triggered $iProcessed threshold(s)";
	}
}


?>

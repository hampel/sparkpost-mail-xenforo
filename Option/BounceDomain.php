<?php namespace Hampel\SparkPostMail\Option;

use SparkPost\SparkPostException;
use XF\Option\AbstractOption;

class BounceDomain extends AbstractOption
{
	public static function renderSelect(\XF\Entity\Option $option, array $htmlParams)
	{
		$data = self::getSelectData($option, $htmlParams);

		return self::getTemplater()->formSelectRow(
			$data['controlOptions'], $data['choices'], $data['rowOptions']
		);
	}

	protected static function getSelectData(\XF\Entity\Option $option, array $htmlParams)
	{
		$choices = self::getBounceDomainOptionsData(true, 'option');

		$choices = array_map(function($v) {
			$v['label'] = \XF::escapeString($v['label']);
			return $v;
		}, $choices);

		return [
			'choices' => $choices,
			'controlOptions' => self::getControlOptions($option, $htmlParams),
			'rowOptions' => self::getRowOptions($option, $htmlParams)
		];
	}

	public static function getBounceDomainOptionsData()
	{
		$choices = [
			0 => ['_type' => 'option', 'value' => '', 'label' => '- Default -']
		];

		$apikey = ApiKey::get();
		if (!empty($apikey))
		{
			/** @var \Hampel\SparkPostMail\SubContainer\SparkPostApi $sp */
			$sp = \XF::app()->get('sparkpostmail');

			try
			{
				$body = $sp->getBounceDomains();
				$domains = $body['results'] ?? 'null';
			}
			catch (SparkPostException $e)
			{
				return [];
			}

			foreach ($domains AS $domain)
			{
				$choices[] = [
					'value' => $domain['domain'],
					'label' => $domain['domain'],
					'type' => 'option'
				];
			}
		}

		return $choices;
	}

	public static function get()
	{
		return \XF::options()->sparkpostmailBounceDomain;
	}
}

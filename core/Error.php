<?php

final class TriggMine_Error
{
	private static $_errors = array(
		'NoError'                       => 0,
		'UnhandledException'            => 99,         // непредвиденная ошибка в работе метода
		'InvalidRequestFormat'          => 100,         // неправильный формат запроса: не JSON

		'TokenNotFound'                 => 110,         // входной параметр не найден: Token
		'TokenInvalidType'              => 111,         // неправильный тип параметра: Token
		'TokenUnsupportedValue'         => 112,         // не поддерживаемое значение либо формат

		'MethodNotFound'                => 120,         // входной параметр не найден: Method
		'MethodInvalidType'             => 121,         // неправильный тип параметра: Method
		'MethodUnsupportedValue'        => 122,         // не поддерживаемое значение либо формат

		'DataNotFound'                  => 130,         // входной параметр не найден: Data
		'DataInvalidType'               => 131,         // неправильный тип параметра: Data

		'BuyerIdNotFound'               => 140,         // входной параметр не найден: BuyerId
		'BuyerIdInvalidType'            => 141,         // неправильный тип параметра: BuyerId
		'BuyerIdUnsupportedValue'       => 142,         // не поддерживаемое значение либо формат
		'BuyerIdUnregistered'           => 143,         // отсутствует в базе

		'CartIdNotFound'                => 150,         // входной параметр не найден: CartId
		'CartIdInvalidType'             => 151,         // неправильный тип параметра: CartId
		'CartIdUnsupportedValue'        => 152,         // не поддерживаемое значение либо формат
		'CartIdUnregistered'            => 153,         // отсутствует в базе

		'BuyerEmailNotFound'            => 160,         // входной параметр не найден: BuyerEmail
		'BuyerEmailInvalidType'         => 161,         // неправильный тип параметра: BuyerEmail
		'BuyerEmailUnsupportedValue'    => 162,         // не поддерживаемое значение либо формат
		'BuyerEmailInvalidSyntax'       => 163,         // мыло не проходит проверку регулярным выражением

		'CartItemIdNotFound'            => 170,         // входной параметр не найден: CartItemId
		'CartItemIdInvalidType'         => 171,         // неправильный тип параметра: CartItemId
		'CartItemIdUnsupportedValue'    => 172,         // не поддерживаемое значение либо формат
		'CartItemIdIsNull'              => 173,         //

		'CartStateNotFound'             => 180,         // входной параметр не найден: CartState
		'CartStateInvalidType'          => 181,         // неправильный тип параметра: CartState
		'CartStateUnsupportedValue'     => 182,         // не поддерживаемое значение либо формат

		'RedirectIdNotFound'            => 190,
		'RedirectIdInvalidType'         => 191,
		'RedirectIdUnsupportedValue'    => 192,
		'RedirectIdInvalidFormat'       => 193,
		'RedirectIdUnregistered'        => 194,

		'LogIdNotFound'                 => 210,
		'LogIdInvalidType'              => 211,
		'LogIdUnsupportedValue'         => 212,
		'LogIdInvalidFormat'            => 213,
		'LogIdUnregistered'             => 214,

		'CartUrlNotFound'               => 220,
		'CartUrlInvalidType'            => 221,
		'CartUrlUnsupportedValue'       => 222,
		'CartUrlInvalidSyntax'          => 223,

		'CartIsClosed'                  => 230,

		'BuyerBirthdayNotFound'         => 270,
		'BuyerBirthdayInvalidType'      => 271,
		'BuyerBirthdayUnsupportedValue' => 272,
		'BuyerRegStartNotFound'         => 273,
		'BuyerRegStartInvalidType'      => 274,
		'BuyerRegStartUnsupportedValue' => 275,
		'BuyerRegEndNotFound'           => 276,
		'BuyerRegEndInvalidType'        => 277,
		'BuyerRegEndUnsupportedValue'   => 278,

		'UrlNotFound'                   => 280,
		'UrlInvalidType'                => 281,
		'UrlUnsupportedValue'           => 282,

		'SpanNotFound'                  => 290,
		'SpanInvalidType'               => 291,
		'SpanUnsupportedValue'          => 292,

		'ImportTaskAlreadyExists'       => 300,

		'OffsetNotFound'                => 310,
		'OffsetInvalidType'             => 311,
		'OffsetUnsupportedValue'        => 312,

		'NextNotFound'                  => 320,
		'NextInvalidType'               => 321,
		'NextUnsupportedValue'          => 322,
	);

	private function __construct()
	{
	}

	public static function isError($code)
	{
		return $code > 0;
	}

	public static function getByCode($code)
	{
		return array_search($code, self::$_errors);
	}

	private function __clone()
	{
	}
}
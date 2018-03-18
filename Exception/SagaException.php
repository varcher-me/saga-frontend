<?php
/**
 * Created by PhpStorm.
 * User: varcher
 * Date: 2018/3/14
 * Time: 下午11:13
 */


class SystemException extends Exception
{

}

class MySQLException extends SystemException
{

}

class SagaRedisException extends SystemException
{

}

class FileException extends SystemException
{

}

class ApplicationException extends Exception
{

}

class NoFilePackedException extends ApplicationException
{

}
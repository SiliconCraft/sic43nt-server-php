<?php

/*
 * LICENSE: The MIT License (the "License")
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * https://github.com/SiliconCraft/sic43nt-server-php/blob/master/LICENSE.txt
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @copyright 2018 Silicon Craft Technology Co.,Ltd.
 * @license   https://github.com/SiliconCraft/sic43nt-server-php/blob/master/LICENSE.txt
 * @link      https://github.com/SiliconCraft/sic43nt-server-php
 
 * This source code is based on The stream cipher MICKEY (version 1) by Steve Babbage 
 * and Matthew Dodd, published on April 29, 2005. The source file is mainly a direct porting 
 * from mickey_v1.c in folder "C code MICKEY v1 faster" of 
 * http://www.ecrypt.eu.org/stream/ciphers/mickey/mickeysource.zip which is implemented 
 * in C language to PHP. However, there are additional function to handle string of bit 
 * and arithmetic shift operation.
 * 
 * Credit #1: The first PHP porting is done by Tanawat Hongthai in 2016
 * Credit #2: Receive feedback from Silicon Craft customer regarding to issue 
 *            with 32-bit PHP and 64-bit PHP. This lead to modification 
 *            from PHP_INT_MAX to 2147483647 in function "u32sh_r".
 * 
 * For original information regarding to Mickey v1 algorithm, please refer to 
 * http://www.ecrypt.eu.org/stream/ciphers/mickey/
 *
 */

 class KeyStruct {
	public $R;
	public $S;
}

$R_Mask;
    /* Feedback mask associated with the register R */
$Comp0;
    /* Input mask associated with register S */
$Comp1;
    /* Second input mask associated with register S */
$S_Mask0;
    /* Feedback mask associated with the register S for clock control bit = 0 */
$S_Mask1;
    /* Feedback mask associated with the register S for clock control bit = 1 */


/*
 * Key and message independent initialization. This function will be
 * called once when the program starts.
 */

function init()
{
    /* Initialise the feedback mask associated with register R */
    $GLOBALS['R_Mask'][0] = 0x1d5363d5;
    $GLOBALS['R_Mask'][1] = 0x415a0aac;
    $GLOBALS['R_Mask'][2] = 0x0000d2a8;

    /* Initialise Comp0 */
    $GLOBALS['Comp0'][0]  = 0x6aa97a30;
    $GLOBALS['Comp0'][1]  = 0x7942a809;
    $GLOBALS['Comp0'][2]  = 0x00003fea;

    /* Initialise Comp1 */
    $GLOBALS['Comp1'][0]  = 0xdd629e9a;
    $GLOBALS['Comp1'][1]  = 0xe3a21d63;
    $GLOBALS['Comp1'][2]  = 0x00003dd7;

    /* Initialise the feedback masks associated with register S */
    $GLOBALS['S_Mask0'][0] = 0x9ffa7faf;
    $GLOBALS['S_Mask0'][1] = 0xaf4a9381;
    $GLOBALS['S_Mask0'][2] = 0x00005802;

    $GLOBALS['S_Mask1'][0] = 0x4c8cb877;
    $GLOBALS['S_Mask1'][1] = 0x4911b063;
    $GLOBALS['S_Mask1'][2] = 0x0000c52b;
}

/* The following routine clocks register R in ctx with given input and control bits */

function CLOCK_R($ctx, $input_bit, $control_bit)
{
    /* Initialise the variables */
	/* r_79 ^ input bit */
    $Feedback_bit = (u32sh_r($ctx->R[2], 15) & 1) ^ $input_bit;  
	/* Respectively, carry from R[0] into R[1] and carry from R[1] into R[2] */
    $Carry0 = u32sh_r($ctx->R[0], 31) & 1;
    $Carry1 = u32sh_r($ctx->R[1], 31) & 1;

    if ($control_bit)
    {
        /* Shift and xor */
        $ctx->R[0] ^= ($ctx->R[0] << 1);
        $ctx->R[1] ^= ($ctx->R[1] << 1) ^ $Carry0;
        $ctx->R[2] ^= ($ctx->R[2] << 1) ^ $Carry1;
    }
    else
    {
        /* Shift only */
        $ctx->R[0] = ($ctx->R[0] << 1);
        $ctx->R[1] = ($ctx->R[1] << 1) ^ $Carry0;
        $ctx->R[2] = ($ctx->R[2] << 1) ^ $Carry1;
    }

    /* Implement feedback into the various register stages */
    if ($Feedback_bit)
    {
        $ctx->R[0] ^= $GLOBALS['R_Mask'][0];
        $ctx->R[1] ^= $GLOBALS['R_Mask'][1];
        $ctx->R[2] ^= $GLOBALS['R_Mask'][2];
    }
}

/* The following routine clocks register S in ctx with given input and control bits */

function CLOCK_S($ctx, $input_bit, $control_bit)
{
    /* Compute the feedback and two carry bits */   
	/* s_79 ^ input bit */
	$Feedback_bit = (u32sh_r($ctx->S[2], 15) & 1) ^ $input_bit;
	
	/* Respectively, carry from S[0] into S[1] and carry from S[1] into S[2] */
    $Carry0 = u32sh_r($ctx->S[0], 31) & 1;
    $Carry1 = u32sh_r($ctx->S[1], 31) & 1;

    /* Derive "s hat" according to the MICKEY v 0.4 specification */
    $ctx->S[0] = ($ctx->S[0] << 1) ^ (($ctx->S[0] ^ $GLOBALS['Comp0'][0]) & (u32sh_r($ctx->S[0], 1) ^ ($ctx->S[1] << 31) ^ $GLOBALS['Comp1'][0]) & 0xfffffffe);
    $ctx->S[1] = ($ctx->S[1] << 1) ^ (($ctx->S[1] ^ $GLOBALS['Comp0'][1]) & (u32sh_r($ctx->S[1], 1) ^ ($ctx->S[2] << 31) ^ $GLOBALS['Comp1'][1])) ^ $Carry0;
    $ctx->S[2] = ($ctx->S[2] << 1) ^ (($ctx->S[2] ^ $GLOBALS['Comp0'][2]) & (u32sh_r($ctx->S[2], 1) ^ $GLOBALS['Comp1'][2]) & 0x7fff) ^ $Carry1;

    /* Apply suitable feedback from s_79 */
    if ($Feedback_bit)
    {
        if ($control_bit)
        {
            $ctx->S[0] ^= $GLOBALS['S_Mask1'][0];
            $ctx->S[1] ^= $GLOBALS['S_Mask1'][1];
            $ctx->S[2] ^= $GLOBALS['S_Mask1'][2];
        }
        else
        {
            $ctx->S[0] ^= $GLOBALS['S_Mask0'][0];
            $ctx->S[1] ^= $GLOBALS['S_Mask0'][1];
            $ctx->S[2] ^= $GLOBALS['S_Mask0'][2];
        }
    }
}

/* 
 * The following routine implements a clock of the keystream generator.  The parameter mixing is set to 0
 * or a non-zero value to determine whether mixing (from s_40) is not/is applied; the parameter input_bit
 * is used to specify any input bit to the generator 
 */

function CLOCK_KG ($ctx, $mixing, $input_bit)
{
    $Keystream_bit = ($ctx->R[0] ^ $ctx->S[0]) & 1;
	/* Keystream bit to be returned (only valid if mixing = 0 and input_bit = 0 */
    $control_bit_r = (u32sh_r($ctx->S[0], 27) ^ u32sh_r($ctx->R[1], 21)) & 1;
	/* The control bit for register R */
    $control_bit_s = (u32sh_r($ctx->S[1], 21) ^ u32sh_r($ctx->R[0], 26)) & 1;
	/* The control bit for register S */

    if ($mixing) {
		CLOCK_R ($ctx, (u32sh_r($ctx->S[1], 8) & 1) ^ $input_bit, $control_bit_r);
	} else {
		CLOCK_R ($ctx, $input_bit, $control_bit_r);
	}
  
    CLOCK_S ($ctx, $input_bit, $control_bit_s);

    return $Keystream_bit;
}

/*
 * This routine set up for both "key" and "iv" value.
 * This slightly difference from the origial C code, 
 * which set up "key" and "iv" using separated function.  
 */

function setup($ctx, $key, $iv)
{
	$keysize = strlen($key);
	$ivsize = strlen($iv);
	
	$iv = strrev($iv);
	$key = strrev($key);
    /* Initialise R and S to all zeros */
    for ($i = 0; $i < 3; $i++)
    {
        $ctx->R[$i] = 0;
        $ctx->S[$i] = 0;
    }

    /* Load in IV */
    for ($i = 0; $i < $ivsize; $i++)
    {
        $iv_or_key_bit = intval($iv[$i]) & 1; /* Adopt usual, perverse, labelling order */
        CLOCK_KG ($ctx, 1, $iv_or_key_bit);
    }

    /* Load in K */
    for ($i = 0; $i < $keysize; $i++)
    {
        $iv_or_key_bit = intval($key[$i]) & 1; /* Adopt usual, perverse, labelling order */
        CLOCK_KG ($ctx, 1, $iv_or_key_bit);
    }

    /* Preclock */
    for ($i = 0; $i < 80; $i++) 
	{
		CLOCK_KG ($ctx, 1, 0);
	}
}

function keystream($key, $iv, $length)                 /* Length of keystream in bytes. */
{
	$keystream;
	$resoure = "";
	$ctx = new KeyStruct();
	init();
	setup($ctx, $key, $iv);
	
    for ($i = 0; $i < $length; $i++)
    {
        $keystream = 0;

        for ($j = 0; $j < 8; $j++)
		{
			$keystream ^= CLOCK_KG ($ctx, 0, 0) << (7-$j);
		}
		$byte = dechex($keystream); // convert char to bit string
		$binary = substr("00",0,2 - strlen($byte)) . $byte; // 4 bit packed
		$resoure .= $binary;
    }
	return strtoupper($resoure);
}

function hexbit($hex_string) {    
    $binary = "";
    $end = strlen($hex_string);
    for($i = 0 ; $i < $end; $i++){
        $byte = decbin(hexdec($hex_string[$i])); // convert char to bit string
        $binary .= substr("00000000",0,4 - strlen($byte)) . $byte; // 4 bit packed
    }
	return strtoupper($binary);
}

function decbit($dec_string) { 
    $byte = decbin($dec_string); // convert char to bit string
    $binary = substr("00000000000000000000000000000000",0,32 - strlen($byte)) . $byte; // 4 bit packed
	
    return strtoupper($binary);
}

function u32sh_r($int, $shft) { 
    return ( $int >> $shft )   //Arithmetic right shift
        & ( 2147483647 >> ( $shft - 1 ) );   //Deleting unnecessary bits
        // Magic Number 2147483647 is refer to 7FFFFFFFh.
        // Cannot use PHP_INT_MAX due to dependency with PHP 32-bit or 64-bit
        // which lead to incorrect result.
}

?>
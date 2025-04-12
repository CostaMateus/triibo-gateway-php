<?php

declare(strict_types = 1);

namespace App\Fixers;

use SplFileInfo;
use PhpCsFixer\Fixer\FixerInterface;
use PhpCsFixer\Tokenizer\{Token, Tokens};
use PhpCsFixer\FixerDefinition\{FixerDefinition, FixerDefinitionInterface};

final class ArraySpacingFixer implements FixerInterface
{
    public function getName() : string
    {
        return "Costa/array_spacing";
    }

    public function getDefinition() : FixerDefinitionInterface
    {
        return new FixerDefinition(
            "Ensure spaces after '[' and before ']' in arrays.",
            []
        );
    }

    public function isCandidate( Tokens $tokens ) : bool
    {
        return $tokens->isTokenKindFound( "[" ) || $tokens->isTokenKindFound( "]" );
    }

    public function isRisky() : bool
    {
        return false;
    }

    public function getPriority() : int
    {
        return 0;
    }

    public function supports( SplFileInfo $file ) : bool
    {
        return true;
    }

    public function fix( SplFileInfo $file, Tokens $tokens ) : void
    {
        for ( $i = 0; $i < $tokens->count(); $i++ )
        {
            $token = $tokens[ $i ];

            if ( $token->getContent() === "[" )
            {
                $after = $i + 1;

                if ( $tokens[ $after ]->isWhitespace() )
                {
                    $tokens[ $after ] = new Token( [ T_WHITESPACE, " " ] );
                }
                else
                {
                    $tokens->insertAt( $after, new Token( [ T_WHITESPACE, " " ] ) );
                    $i++;
                }
            }

            if ( $token->getContent() === "]" )
            {
                $before = $i - 1;

                if ( $tokens[ $before ]->isWhitespace() )
                {
                    $tokens[ $before ] = new Token( [ T_WHITESPACE, " " ] );
                }
                else
                {
                    $tokens->insertAt( $i, new Token( [ T_WHITESPACE, " " ] ) );

                    $i++;
                }
            }
        }
    }
}

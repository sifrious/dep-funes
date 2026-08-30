<?php

declare(strict_types=1);

namespace Sifrious\Funes\Diagram;

final class LocalCompactEnglishParser implements GrammarParser
{
    private const VERB_LEXICON = [
        'am', 'are', 'be', 'been', 'being', 'eat', 'eats', 'go', 'goes', 'have', 'has', 'is', 'jump', 'jumps',
        'run', 'runs', 'said', 'say', 'says', 'see', 'sees', 'was', 'were', 'write', 'writes',
    ];

    private const DETERMINERS = ['a', 'an', 'the', 'this', 'that', 'these', 'those', 'my', 'your', 'our', 'their'];

    private const PRONOUNS = ['he', 'her', 'him', 'i', 'it', 'she', 'they', 'them', 'we', 'you'];

    public function name(): string
    {
        return 'local-compact-english';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function parse(string $sentence): ParsedSentence
    {
        preg_match_all("/[A-Za-z']+|[.,!?;:]/", $sentence, $matches);
        $words = $matches[0];

        if ($words === []) {
            return new ParsedSentence([], [], ['No words were found in the sentence.']);
        }

        $tokens = [];
        foreach ($words as $index => $word) {
            $id = $index + 1;
            $tokens[] = [
                'id' => $id,
                'text' => $word,
                'pos' => $this->guessPartOfSpeech($word),
            ];
        }

        $verbIndex = $this->findVerbIndex($tokens);
        $subjectIndex = $this->findSubjectIndex($tokens, $verbIndex);
        $objectIndex = $this->findObjectIndex($tokens, $verbIndex);
        $dependencies = [];
        $warnings = [];

        if ($verbIndex === null) {
            $root = 1;
            $warnings[] = 'No clear verb was found; using the first token as the root.';
        } else {
            $root = $tokens[$verbIndex]['id'];
            $dependencies[] = ['from' => $root, 'to' => 0, 'label' => 'root'];
        }

        if ($subjectIndex !== null && $verbIndex !== null) {
            $dependencies[] = [
                'from' => $tokens[$subjectIndex]['id'],
                'to' => $tokens[$verbIndex]['id'],
                'label' => 'nsubj',
            ];
        } else {
            $warnings[] = 'No clear subject was found.';
        }

        if ($objectIndex !== null && $verbIndex !== null) {
            $dependencies[] = [
                'from' => $tokens[$objectIndex]['id'],
                'to' => $tokens[$verbIndex]['id'],
                'label' => 'obj',
            ];
        }

        foreach ($tokens as $idx => $token) {
            if ($token['pos'] !== 'DET') {
                continue;
            }

            $head = $this->nextNounIndex($tokens, $idx);
            if ($head !== null) {
                $dependencies[] = [
                    'from' => $token['id'],
                    'to' => $tokens[$head]['id'],
                    'label' => 'det',
                ];
            }
        }

        return new ParsedSentence($tokens, $dependencies, array_values(array_unique($warnings)));
    }

    /**
     * @param  list<array{id:int,text:string,pos:string}>  $tokens
     */
    private function findVerbIndex(array $tokens): ?int
    {
        foreach ($tokens as $index => $token) {
            if ($token['pos'] === 'VERB') {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param  list<array{id:int,text:string,pos:string}>  $tokens
     */
    private function findSubjectIndex(array $tokens, ?int $verbIndex): ?int
    {
        $limit = $verbIndex ?? count($tokens);
        for ($i = 0; $i < $limit; $i++) {
            if (in_array($tokens[$i]['pos'], ['NOUN', 'PRON'], true)) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param  list<array{id:int,text:string,pos:string}>  $tokens
     */
    private function findObjectIndex(array $tokens, ?int $verbIndex): ?int
    {
        if ($verbIndex === null) {
            return null;
        }

        for ($i = $verbIndex + 1; $i < count($tokens); $i++) {
            if (in_array($tokens[$i]['pos'], ['NOUN', 'PRON'], true)) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param  list<array{id:int,text:string,pos:string}>  $tokens
     */
    private function nextNounIndex(array $tokens, int $start): ?int
    {
        for ($i = $start + 1; $i < count($tokens); $i++) {
            if ($tokens[$i]['pos'] === 'NOUN') {
                return $i;
            }
        }

        return null;
    }

    private function guessPartOfSpeech(string $word): string
    {
        $lower = strtolower($word);

        if (in_array($lower, self::DETERMINERS, true)) {
            return 'DET';
        }

        if (in_array($lower, self::PRONOUNS, true)) {
            return 'PRON';
        }

        if (in_array($lower, self::VERB_LEXICON, true) || str_ends_with($lower, 'ed') || str_ends_with($lower, 'ing')) {
            return 'VERB';
        }

        if (preg_match('/^[.,!?;:]$/', $word) === 1) {
            return 'PUNCT';
        }

        return 'NOUN';
    }
}

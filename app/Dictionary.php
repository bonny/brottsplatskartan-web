<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;

class Dictionary extends Model
{
    /**
     * Hämta alla ord och synonymer
     *
     * @return array Array med alla ord och synonymer
     */
    public static function getAllWordsAndSynonyms()
    {
        $wordsAndSynonyms = self::select(DB::raw('id, CONCAT_WS(",", word, synonyms) as words'))->get();
        $arrWords = [];
        foreach ($wordsAndSynonyms as $oneWordAndSynonyms) {
            $arrWords = array_merge($arrWords, explode(',', $oneWordAndSynonyms->words));
        }
        $arrWords = array_map('trim', $arrWords);
        $arrWords = array_filter($arrWords);
        $arrWords = array_map('mb_strtolower', $arrWords);
        $arrWords = array_unique($arrWords);
        sort($arrWords, SORT_LOCALE_STRING);

        return $arrWords;
    }

    /**
     * Get all words foud in text
     *
     * @param string $text Block of text, like the full text for an event (with stripped tags and so on)
     * @return words collection
     */
    public static function getWordsInText($text)
    {
        $arrWords = self::getAllWordsAndSynonyms();

        // Gör texten att kolla + orden att kolla efter lowercase + trim
        $text = trim($text);
        $text = mb_strtolower($text);

        $arrWords = array_map('trim', $arrWords);
        $arrWords = array_map('mb_strtolower', $arrWords);

        // Array med ord/fras som matchar
        $arrMatchingWords = [];

        foreach ($arrWords as $oneWord) {
            if (strpos($text, $oneWord) !== false) {
                $arrMatchingWords[] = $oneWord;
            }
        }

        // Hämta orden från databasen så vi får ord, synonymer, och beskrivning
        $wordsCollection = collect();
        foreach ($arrMatchingWords as $oneMatchedWord) {
            $wordsCollection = $wordsCollection->merge(self::whereRaw('FIND_IN_SET("' . $oneMatchedWord . '", CONCAT_WS(",", word, synonyms))')->get());
        }

        return $wordsCollection;
    }
}

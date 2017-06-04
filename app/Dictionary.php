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

        $text = str_word_count(utf8_decode($text), 1);
        $text = array_map('utf8_encode', $text);
        $text = array_map('trim', $text);
        $text = array_map('mb_strtolower', $text);
        $text = array_filter($text);
        #dd($text);

        if (isset($_GET["debug3"])) {
            dd($text);
        }

        // $wordsIntersect är en array som innehåller ordliste-orden som finns i texten
        $wordsIntersect = array_intersect($arrWords, $text);
        #dd($wordsIntersect);

        // Hämta orden från databasen så vi får ord, synonymer, och beskrivning
        $wordsCollection = collect();
        foreach ($wordsIntersect as $oneIntersectedWord) {
            $wordsCollection = $wordsCollection->merge(self::whereRaw('FIND_IN_SET("' . $oneIntersectedWord . '", CONCAT_WS(",", word, synonyms))')->get());
        }

        #dd($wordsCollection);

        return $wordsCollection;
    }
}

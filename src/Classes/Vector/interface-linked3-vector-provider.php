<?php
/**
 * Vector Provider Strategy Interface — abstracts vector store backends.
 *
 * 4 providers: Pinecone / Qdrant / OpenAI Embeddings / Local (SQLite-vec).
 * Used by the RAG layer to index WP content and retrieve context.
 *
 * @package Linked3
 * @subpackage Classes\Vector
 */

namespace Linked3\Classes\Vector;

if (!defined('ABSPATH')) {
    exit;
}

interface Linked3_Vector_Provider_Interface
{
    /** @return string slug */
    public function slug();

    /**
     * Connect / verify credentials.
     *
     * @param array $config
     * @return array{ok:bool, message:string}
     */
    public function connect(array $config);

    /**
     * Create an index/collection.
     *
     * @param string $name
     * @param int    $dimensions
     * @param array  $config
     * @return array{ok:bool, message:string}
     */
    public function create_index($name, $dimensions, array $config);

    /**
     * Upsert vectors.
     *
     * @param string $index
     * @param array  $vectors [{id, embedding, metadata}]
     * @param array  $config
     * @return array{ok:bool, message:string}
     */
    public function upsert($index, array $vectors, array $config);

    /**
     * Query top-K nearest neighbors.
     *
     * @param string $index
     * @param float[] $query_vector
     * @param int    $top_k
     * @param array  $filters
     * @param array  $config
     * @return array<int,array{id:string, score:float, metadata:array}>
     */
    public function query($index, array $query_vector, $top_k = 5, array $filters = [], array $config = []);

    /**
     * Delete vectors by ID or filter.
     *
     * @param string $index
     * @param array  $ids
     * @param array  $config
     * @return array{ok:bool, message:string}
     */
    public function delete($index, array $ids, array $config);

    /**
     * Generate an embedding for text (using the linked AI Dispatcher).
     *
     * @param string $text
     * @param array  $config
     * @return float[]|\WP_Error
     */
    public function embed($text, array $config);
}

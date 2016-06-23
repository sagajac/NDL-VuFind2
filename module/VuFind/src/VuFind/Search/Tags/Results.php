<?php
/**
 * Tags aspect of the Search Multi-class (Results)
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  Search_Tags
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFind\Search\Tags;
use VuFind\Search\Base\Results as BaseResults;

/**
 * Search Tags Results
 *
 * @category VuFind
 * @package  Search_Tags
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Results extends BaseResults
{
    /**
     * Process a fuzzy tag query.
     *
     * @param string $q Raw query
     *
     * @return string
     */
    protected function formatTagQuery($q)
    {
        // Change unescaped asterisks to percent signs to translate more common
        // wildcard character into format used by database.
        return preg_replace('/(?<!\\\\)\\*/', '%', $q);
    }

    /**
     * Return resources associated with the user tag query, using fuzzy matching.
     *
     * @return array
     */
    protected function performFuzzyTagSearch()
    {
        $table = $this->getTable('Tags');
        $query = $this->formatTagQuery($this->getParams()->getDisplayQuery());
        $rawResults = $table->fuzzyResourceSearch(
            $query, null, $this->getParams()->getSort()
        );

        // How many results were there?
        $this->resultTotal = count($rawResults);

        // Apply offset and limit if necessary!
        $limit = $this->getParams()->getLimit();
        if ($this->resultTotal > $limit) {
            $rawResults = $table->fuzzyResourceSearch(
                $query, null, $this->getParams()->getSort(),
                $this->getStartRecord() - 1, $limit
            );
        }

        return $rawResults->toArray();
    }

    /**
     * Return resources associated with the user tag query, using exact matching.
     *
     * @return array
     */
    protected function performExactTagSearch()
    {
        $table = $this->getTable('Tags');
        $tag = $table->getByText($this->getParams()->getDisplayQuery());
        if (empty($tag)) {
            $this->resultTotal = 0;
            return [];
        }
        $rawResults = $tag->getResources(null, $this->getParams()->getSort());

        // How many results were there?
        $this->resultTotal = count($rawResults);

        // Apply offset and limit if necessary!
        $limit = $this->getParams()->getLimit();
        if ($this->resultTotal > $limit) {
            $rawResults = $tag->getResources(
                null, $this->getParams()->getSort(), $this->getStartRecord() - 1,
                $limit
            );
        }

        return $rawResults->toArray();
    }

    /**
     * Support method for performAndProcessSearch -- perform a search based on the
     * parameters passed to the object.
     *
     * @return void
     */
    protected function performSearch()
    {
        // There are two possibilities here: either we are in "fuzzy" mode because
        // we are coming in from a search, in which case we want to do a fuzzy
        // search that supports wildcards, or else we are coming in from a tag
        // link, in which case we want to do an exact match.
        $hf = $this->getParams()->getHiddenFilters();
        $rawResults = (isset($hf['fuzzy']) && in_array('true', $hf['fuzzy']))
            ? $this->performFuzzyTagSearch()
            : $this->performExactTagSearch();

        // Retrieve record drivers for the selected items.
        $callback = function ($row) {
            return ['id' => $row['record_id'], 'source' => $row['source']];
        };
        $this->results = $this->getServiceLocator()->get('VuFind\RecordLoader')
            ->loadBatch(array_map($callback, $rawResults));
    }

    /**
     * Returns the stored list of facets for the last search
     *
     * @param array $filter Array of field => on-screen description listing
     * all of the desired facet fields; set to null to get all configured values.
     *
     * @return array        Facets data arrays
     */
    public function getFacetList($filter = null)
    {
        // Facets not supported:
        return [];
    }
}

<?php
/**
 * Helper functions for various tasks in the application.
 */

if ( !function_exists( "get_marketplace_categories" ) )
{
    /**
     * Retrieves all marketplace categories and organizes them by ID.
     *
     * @return  array   An array of categories indexed by their IDs.
     */
    function get_marketplace_categories() : array
    {
        $page       = 1;
        $aux_cats   = [];
        $categories = [];

        do
        {
            $data     = Category::all( [ "per_page" => 100, "page" => $page ] )->toArray();
            $aux_cats = array_merge( $aux_cats, $data );
            $page++;
        }
        while ( count( $data ) == 100 );

        foreach ( $aux_cats as $cat )
            $categories[ $cat->id ] = $cat;

        ksort( $categories );

        return $categories;
    }
}

if( !function_exists( "add_sub_category" ) )
{
    /**
     * Recursively adds a child category to its parent in a hierarchical structure.
     *
     * This function searches for the parent of a given child category in the result array
     * or its nested children, and adds the child to the appropriate parent's children array.
     *
     * @param   array   &$result    The array of categories to search through. Passed by reference.
     * @param   array   $child      The child category to be added.
     * @return  void
     */
    function add_sub_category( array &$result, array $child ) : void
    {
        foreach ( $result as &$item )
        {
            if ( $item[ "id" ] === $child[ "parent" ] )
            {
                unset( $child[ "parent" ] );

                $child[ "specifications" ] = [ [
                    "attributeName" => "NONE",
                    "required"      => false,
                    "attributeValues" => [ [ "valueName" => "NONE" ] ]
                ] ];

                $item[ "children" ][]      = $child;
                return;
            }
            elseif ( !empty( $item[ "children" ] ) )
            {
                add_sub_category( result: $item[ "children" ], child: $child );
            }
        }
    }
}

if ( !function_exists( "format_categories" ) )
{
    /**
     * Formats a flat array of category items into a hierarchical structure.
     *
     * @param   array   $items  Array of category items.
     * @return  array           Array of categories organized in a hierarchical structure.
     */
    function format_categories( array $items ) : array
    {
        $result = [];

        foreach ( $items as $item )
        {
            if ( $item[ "parent" ] === "0" )
            {
                unset( $item[ "parent" ] );

                // Root level item.
                $result[ $item[ "id" ] ] = $item;
            }
            else
            {
                // Child item, recursively find the parent and add to its children.
                add_sub_category( result: $result, child: $item );
            }
        }

        return $result;
    }
}

if ( !function_exists( "format_categories_to_vtex" ) )
{
    /**
     * Formats category data for VTEX integration.
     *
     * @param   array   $data   Raw category data.
     * @return  array           Formatted and hierarchically organized category data.
     */
    function format_categories_to_vtex( array $data ) : array
    {
        $categories = [];

        foreach ( $data as $category )
        {
            $categories[] = [
                "id"       => ( string ) $category->id,
                "name"     => $category->name,
                "parent"   => ( string ) $category->parent,
                "children" => [],
            ];
        }

        return format_categories( items: $categories );
    }
}

if ( !function_exists( "save_file" ) )
{
    /**
     * Saves an array of data to a file with a timestamped name.
     *
     * @param   string  $name   The base name of the file (without extension).
     * @param   array   $data   The data to be saved as JSON.
     * @return  void
     */
    function save_file( string $name, array $data )
    {
        $date    = date( "Ymd-his" );
        $encoded = json_encode( $data, JSON_UNESCAPED_SLASHES );

        Storage::put( "{$name}-{$date}.json", $encoded );
    }
}

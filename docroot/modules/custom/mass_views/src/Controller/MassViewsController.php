<?php


namespace Drupal\mass_views\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\mass_content\Entity\Bundle\media\DocumentBundle;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\views\Views;

class MassViewsController extends ControllerBase {

  /**
   * Generates filtered URLs for pages.
   */
  public function pageLinks(Request $request) {
    return $this->generateView('crawler_pages', $request);
  }

  /**
   * Generates filtered URLs for documents.
   */
  public function documentLinks(Request $request) {
    return $this->generateView('crawler_documents', $request);
  }

  /**
   * Helper function to generate the view with pagination and filters.
   */
  private function generateView($view_name, Request $request) {
    // Load the view.
    $view = Views::getView($view_name);
    if (is_object($view)) {
      // Set up the display and limit the number of items per page.
      $view->setDisplay('default');
      $items_per_page = 500;
      $view->setItemsPerPage($items_per_page); // Limit to 500 results per page.

      // Handle query string parameters for filtering.
      $query_parameters = $request->query->all();

      // Apply all query string filters using the proper method.
      $view->setExposedInput($query_parameters);

      // Get the page number from query string (default to page 0).
      $current_page = $request->query->get('page', 0);
      $view->setCurrentPage($current_page);

      // Execute the view.
      $view->execute();

      if ($view->result) {
        // Build the output as an HTML list of links.
        $output = '<ul>';
        foreach ($view->result as $row) {
          $href = $row->_entity->toUrl()->toString();
          $text = $row->_entity->getTitle();
          if ($row->_entity instanceof DocumentBundle) {
            $href = $row->_entity->toUrl()->toString() . '/download';
            $text =  $row->_entity->getTitle()->getString();
          }
          $output .= '<li><a href="' . $href . '">' . $text . '</a></li>';
        }
        $output .= '</ul>';

        // Get total number of items in the view.
        $total_pages = $view->pager->getPagerTotal();
        $pagination = '';
        if ($total_pages > 1) {
          $pagination .= '<div class="pagination">';
          // Previous page link
          if ($current_page > 0) {
            $prev_page = $current_page - 1;
            $pagination .= '<a href="?' . http_build_query(array_merge($query_parameters, ['page' => $prev_page])) . '">Previous</a>';
          }
          // Next page link
          if ($current_page < $total_pages - 1) {
            $next_page = $current_page + 1;
            $pagination .= ' <a href="?' . http_build_query(array_merge($query_parameters, ['page' => $next_page])) . '">Next</a>';
          }
          $pagination .= '</div>';
        }

        // Return raw HTML response with pagination.
        return new Response($output . $pagination, 200, ['Content-Type' => 'text/html']);
      }
    }

    // Return an empty response if the view doesn't exist.
    return new Response('No results found', 404);
  }
}

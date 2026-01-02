<?php
// Dodaj tę metodę do ImportController

/**
 * Czyści historię importu użytkownika
 */
public function clearHistory(): void {
    $this->requireAuth();
    
    if (!$this->isPost()) {
        $this->redirect('/import');
        return;
    }
    
    $userId = $this->getCurrentUserId();
    
    try {
        $stmt = $this->database->prepare("DELETE FROM import_history WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        $_SESSION['success'] = 'Historia importu została wyczyszczona. Możesz teraz zaimportować transakcje ponownie.';
    } catch (Exception $e) {
        $_SESSION['error'] = 'Błąd podczas czyszczenia historii importu';
    }
    
    $this->redirect('/import');
}

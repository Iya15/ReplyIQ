export interface ChatbotConfig {
  public_id:        string;
  name:             string;
  logo_url:         string | null;
  avatar_url:       string | null;
  primary_color:    string;
  text_color:       string;
  font_family:      string;
  welcome_message:  string;
  placeholder_text: string;
  position:         'bottom-right' | 'bottom-left';
  theme:            'light' | 'dark';
  show_branding:    boolean;
}

export interface Source {
  id:    string;
  title: string;
  url?:  string | null;
}

export interface Message {
  id:         string;
  role:       'user' | 'assistant';
  content:    string;
  status:     'pending' | 'complete';
  sources:    Source[];
  confidence: number | null;
  created_at: string;
}

export interface Session {
  token:          string;
  conversationId: string;
  visitorId:      string;
  apiBase:        string;
}

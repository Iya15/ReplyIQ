'use client';

import { use, useState } from 'react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { ConversationList } from '@/components/conversations/conversation-list';
import { ConversationDetail, ConversationDetailEmpty } from '@/components/conversations/conversation-detail';

interface PageProps {
  params: Promise<{ id: string }>;
}

export default function ConversationsPage({ params }: PageProps) {
  const { id } = use(params);
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [mobileView, setMobileView] = useState<'list' | 'detail'>('list');

  function handleSelect(conversationId: string) {
    setSelectedId(conversationId);
    setMobileView('detail');
  }

  return (
    <div className="flex h-full overflow-hidden">
      {/* Left panel — list */}
      <div
        className={`flex flex-col w-full md:w-1/3 md:border-r shrink-0 ${
          mobileView === 'detail' ? 'hidden md:flex' : 'flex'
        }`}
      >
        <ConversationList
          chatbotId={id}
          selectedId={selectedId}
          onSelect={handleSelect}
        />
      </div>

      {/* Right panel — detail */}
      <div
        className={`flex flex-col flex-1 min-w-0 ${
          mobileView === 'list' ? 'hidden md:flex' : 'flex'
        }`}
      >
        {/* Mobile back button */}
        {mobileView === 'detail' && (
          <div className="md:hidden border-b px-3 py-2 shrink-0">
            <Button
              size="sm"
              variant="ghost"
              onClick={() => setMobileView('list')}
              className="-ml-1"
            >
              <ArrowLeft className="h-4 w-4 mr-1" />
              Back
            </Button>
          </div>
        )}

        {selectedId ? (
          <ConversationDetail conversationId={selectedId} chatbotId={id} />
        ) : (
          <ConversationDetailEmpty />
        )}
      </div>
    </div>
  );
}

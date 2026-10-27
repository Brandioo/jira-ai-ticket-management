import { FormEvent, useState } from 'react';
import { api } from '../api';
import type { AssistantResponse, DashboardData } from '../types';
import { Icon } from './Icon';

type Message = {
  who: 'user' | 'assistant';
  text: string;
  meta?: string;
};

export function AssistantPage({
  dashboard,
  onChanged,
}: {
  dashboard: DashboardData | null;
  onChanged: () => Promise<void>;
}) {
  const [question, setQuestion] = useState('');
  const [messages, setMessages] = useState<Message[]>([
    {
      who: 'assistant',
      text: 'Hi. I can answer questions and also manage project data: tickets, epics, sprints, statuses, priorities, assignees, descriptions, hours, overtime and workload.',
      meta: 'Project context ready',
    },
  ]);
  const [busy, setBusy] = useState(false);

  const prompts = [
    'Tickets in Sprint 24',
    'Create epic Customer onboarding',
    'Change AIP-103 status to done',
    'How can I create a new sprint?',
  ];

  async function ask(value?: string) {
    const q = (value ?? question).trim();

    if (!q || busy) return;

    setMessages((current) => [
      ...current,
      {
        who: 'user',
        text: q,
      },
    ]);

    setQuestion('');
    setBusy(true);

    try {
      const response: AssistantResponse = await api.ask(
        q,
        dashboard?.active_sprint?.id
      );

      setMessages((current) => [
        ...current,
        {
          who: 'assistant',
          text: response.answer,
          meta:
            response.engine === 'ollama'
              ? 'Ollama local LLM'
              : 'Local project query engine',
        },
      ]);

      if (
        [
          'sprint_created',
          'sprint_started',
          'sprint_completed',
          'issue_created',
          'issue_updated',
        ].includes(response.mode)
      ) {
        await onChanged();
      }
    } catch (err) {
      setMessages((current) => [
        ...current,
        {
          who: 'assistant',
          text:
            err instanceof Error
              ? err.message
              : 'The assistant request failed.',
          meta: 'Error',
        },
      ]);
    } finally {
      setBusy(false);
    }
  }

  function submit(event: FormEvent) {
    event.preventDefault();
    void ask();
  }

  return (
    <section className="assistant-layout">
      <aside className="assistant-info panel">
        <div className="spark large">
          <Icon name="sparkles" />
        </div>

        <span className="eyebrow">AI PROJECT ASSISTANT</span>

        <h2>Ask questions or make project changes.</h2>

        <p>
          The assistant can query project data and perform supported actions:
          create issues or epics, edit issues by key, create/start/complete
          sprints, and analyze workload and sprint data.
        </p>

        <div className="context-box">
          <span>Current context</span>
          <strong>
            {dashboard?.active_sprint?.name ?? 'No active sprint'}
          </strong>
          <small>
            {dashboard?.ticket_counts.total ?? 0} issues loaded
          </small>
        </div>

        <div className="suggestions">
          {prompts.map((prompt) => (
            <button
              key={prompt}
              onClick={() => void ask(prompt)}
            >
              {prompt}
              <span>→</span>
            </button>
          ))}
        </div>
      </aside>

      <div className="chat panel">
        <div className="chat-header">
          <div>
            <strong>Project Assistant</strong>
            <span><i /> Online</span>
          </div>

          <span className="engine-badge">
            AI + project data
          </span>
        </div>

        <div className="messages">
          {messages.map((message, index) => (
            <div
              key={index}
              className={`message ${message.who}`}
            >
              {message.who === 'assistant' && (
                <div className="bot-avatar">✦</div>
              )}

              <div>
                <div className="bubble">
                  {message.text}
                </div>

                {message.meta && (
                  <small>{message.meta}</small>
                )}
              </div>
            </div>
          ))}

          {busy && (
            <div className="message assistant">
              <div className="bot-avatar">✦</div>
              <div className="bubble typing">
                Thinking…
              </div>
            </div>
          )}
        </div>

        <form className="chat-input" onSubmit={submit}>
          <input
            value={question}
            onChange={(event) =>
              setQuestion(event.target.value)
            }
            placeholder="Ask or act: create epic, start sprint, change AIP-103 status…"
          />

          <button className="primary" disabled={busy}>
            Send
          </button>
        </form>
      </div>
    </section>
  );
}
